<?php declare(strict_types=1);

namespace App\Mail\Contact;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class ContactCompleteToUser extends Mailable
{
    use Queueable, SerializesModels;

    public Contact $contact;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->to($this->contact->email, $this->contact->name)
            ->from(Config::get('mail.contact_from'))
            ->text('emails.contact.to_user');
    }
}
