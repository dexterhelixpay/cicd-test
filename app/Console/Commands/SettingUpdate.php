<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class SettingUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setting:update
        {key : The key of the setting}
        {value : The value of the setting}
        {--hash : Hash the value}
        {--encrypt : Encrypt the value}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the value of a setting';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$setting = Setting::where('key', $this->argument('key'))->first()) {
            return $this->getOutput()->error('Setting not found.');
        }

        $value = $this->argument('value');

        if ($this->option('hash')) {
            $value = bcrypt($value);
        } elseif ($this->option('encrypt')) {
            $value = encrypt($value);
        }

        $setting->update(compact('value'));

        $this->table(['Key', 'Value'], [[$setting->key, $setting->value]]);
        $this->getOutput()->success('Setting updated successfully.');

        return 0;
    }
}
