<?php

namespace App\Jobs;

use App\Models\User;
use Aws\Laravel\AwsFacade as AWS;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Throwable;

class AddUserToSecurityGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user model.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The IP address.
     *
     * @var string
     */
    public $ip;

    /**
     * The EC2 client instance.
     *
     * @var \Aws\Ec2\Ec2Client
     */
    private $ec2;

    /**
     * The security group ID.
     *
     * @var string
     */
    private $groupId;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $ip
     * @return void
     */
    public function __construct(User $user, $ip)
    {
        $this->user = $user;
        $this->ip = $ip;

        $this->groupId = config('aws.ec2.security_group');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->user->has_database_access || !$this->groupId) {
            return;
        }

        $ec2 = AWS::createClient('ec2', [
            'credentials' => config('aws.ec2.credentials'),
        ]);

        if (filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $range = 'Ipv6Ranges';
            $cidr = 'CidrIpv6';
            $port = 128;
        } else {
            $range = 'IpRanges';
            $cidr = 'CidrIp';
            $port = 32;
        }

        $result = $ec2->describeSecurityGroups([
            'GroupIds' => [$this->groupId],
        ]);

        if (!$result->count()) {
            return;
        }

        $securityGroup = collect($result->get('SecurityGroups'))->first();
        $ipPermission = collect(data_get($securityGroup, 'IpPermissions'))
            ->first(function ($permission) {
                return data_get($permission, 'IpProtocol') === 'tcp';
            });

        if (!$ipPermission) {
            return;
        }

        $ipRanges = array_merge(
            data_get($ipPermission, 'IpRanges', []),
            data_get($ipPermission, 'Ipv6Ranges', [])
        );

        $ipExists = collect($ipRanges)->contains(function ($ip) use ($cidr, $port) {
            return Arr::has($ip, $cidr) && $ip[$cidr] === "{$this->ip}/{$port}";
        });

        if ($ipExists) {
            return;
        }

        $userIpRanges = collect($ipRanges)->filter(function ($ip) {
            return (int) $this->user->getKey() === (int) data_get($ip, 'Description');
        });

        $userIpRanges->each(function ($ip) use ($ec2) {
            if (Arr::has($ip, 'CidrIpv6')) {
                $range = 'Ipv6Ranges';
                $cidr = 'CidrIpv6';
            } else {
                $range = 'IpRanges';
                $cidr = 'CidrIp';
            }

            $permission = [
                'IpProtocol' => 'tcp',
                'FromPort' => 22,
                'ToPort' => 22,
                $range => [
                    [$cidr => $ip[$cidr]],
                ],
            ];

            try {
                $ec2->revokeSecurityGroupIngressAsync([
                    'GroupId' => $this->groupId,
                    'IpPermissions' => [$permission],
                ]);
            } catch (Throwable) {
                //
            }
        });

        try {
            $ec2->authorizeSecurityGroupIngress([
                'GroupId' => $this->groupId,
                'IpPermissions' => [
                    [
                        'IpProtocol' => 'tcp',
                        'FromPort' => 22,
                        'ToPort' => 22,
                        $range => [
                            [
                                $cidr => "{$this->ip}/{$port}",
                                'Description' => "{$this->user->getKey()} - {$this->user->name}",
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (Throwable) {
            //
        }
    }
}
