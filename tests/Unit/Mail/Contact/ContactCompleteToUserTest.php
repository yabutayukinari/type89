<?php declare(strict_types=1);

namespace Tests\Unit\Mail\Contact;

use App\Mail\Contact\ContactCompleteToUser;
use App\Models\Contact;
use Tests\TestCase;

/**
 * @see ContactCompleteToUser
 */
class ContactCompleteToUserTest extends TestCase
{
    /**
     *
     */
    public function testBuild(): void
    {
        /** @var Contact $contact */
        $contact = Contact::factory()->create();
        $mail = new ContactCompleteToUser($contact);
        $message = $mail->build();

        // 検証
        $this->assertSame([
            [
                'name' => $contact->name,
                'address' => $contact->email
            ]
        ], $message->to);
    }
}
