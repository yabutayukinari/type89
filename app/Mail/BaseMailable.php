<?php declare(strict_types=1);

namespace App\Mail;

use App\Exceptions\NotFoundMailAddressException;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use function count;

/**
 * Base Mailable
 *
 * It's a wraper class of Mailable
 */
class BaseMailable extends Mailable
{
    use Queueable, SerializesModels;

    public static function sendMail(array $args): void
    {
        $class = static::class;
        try {
            Log::info('メール送信開始 ' . $class, $args);
            Mail::send(new $class(...array_values($args)));
            Log::info('メール送信完了 ' . $class, $args);
        } catch (Throwable $e) {
            $args['Message'] = $e->getMessage();
            $args['TraceAsString'] = $e->getTraceAsString();
            Log::error('メール送信失敗 ' . $class, $args);
        }
    }

    /**
     * Send the message using the given mailer.
     *
     * @param Mailer $mailer
     * @return void
     */
    public function send($mailer)
    {
        Container::getInstance()->call('', [], 'build');
        $class = static::class;

        if (count($this->to) === 0) {
            throw new NotFoundMailAddressException('toが登録されていません。 ' . $class);
        }
        if (count($this->from) === 0) {
            throw new NotFoundMailAddressException('fromが登録されていません。 ' . $class);
        }

        $mailer->send($this->buildView(), $this->buildViewData(), function ($message) {
            $this->buildFrom($message)
                ->buildRecipients($message)
                ->buildSubject($message)
                ->buildAttachments($message)
                ->runCallbacks($message);
        });
    }
}
