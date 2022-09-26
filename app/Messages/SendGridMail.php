<?php

namespace App\Messages;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Mail\Mailable;
use SendGrid\Mail\Mail;

class SendGridMail extends Mail
{
    /**
     * Create a new message instance from the given mail.
     *
     * @param  MailMessage|Mailable  $mailable
     * @return static|null
     */
    public static function fromMail($mailable)
    {
        if (!mb_strlen($content = (string) $mailable->render())) {
            return null;
        }

        $mail = new static;
        $mail->setSubject($mailable->subject ?? '(no subject)');
        $mail->addContent('text/html', $content);

        if (count($mailable->cc)) {
            $mail->addCcs(
                collect($mailable->cc)
                    ->mapWithKeys(function ($cc) {
                        return [$cc[0] => $cc[1]];
                    })
                    ->all()
            );
        }

        if (count($mailable->bcc)) {
            $mail->addBccs(
                collect($mailable->bcc)
                    ->mapWithKeys(function ($bcc) {
                        return [$bcc[0] => $bcc[1]];
                    })
                    ->all()
            );
        }

        if (count($mailable->rawAttachments)) {
            foreach ($mailable->rawAttachments as $attachment) {
                $mail->addAttachment(
                    $attachment['data'],
                    data_get($attachment, 'options.mime'),
                    $attachment['name']
                );
            }
        }

        return $mail;
    }
}
