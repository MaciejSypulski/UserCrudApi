<?php

namespace Tests\Feature;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpFaker();
    }


## Testy tworzenia użytkownika (POST /api/users)

    #[Test]
    public function it_can_create_a_user_with_email_addresses()
    {
        $firstName = $this->faker->firstName;
        $lastName = $this->faker->lastName;
        $phoneNumber = $this->faker->e164PhoneNumber(); 
        $primaryEmail = $this->faker->unique()->safeEmail;
        $secondaryEmail = $this->faker->unique()->safeEmail;

        $userData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone_number' => $phoneNumber,
            'emails' => [
                ['email' => $primaryEmail],
                ['email' => $secondaryEmail],
            ],
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertStatus(201) 
                 ->assertJson([
                     'data' => [
                         'first_name' => $firstName,
                         'last_name' => $lastName,
                         'phone_number' => $phoneNumber,
                         'email_addresses' => [
                             ['email' => $primaryEmail],
                             ['email' => $secondaryEmail],
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('users', [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $user = User::where('first_name', $firstName)->first();
        $this->assertCount(2, $user->emailAddresses);
        $this->assertDatabaseHas('email_addresses', ['email' => $primaryEmail, 'user_id' => $user->id]);
        $this->assertDatabaseHas('email_addresses', ['email' => $secondaryEmail, 'user_id' => $user->id]);
    }

    #[Test]
    public function it_returns_validation_errors_for_invalid_user_data()
    {
        $response = $this->postJson('/api/users', [
            'first_name' => '', // Celowo puste, aby wywołać błąd
            'emails' => [['email' => $this->faker->word]] // Niepoprawny format maila
        ]);

        $response->assertStatus(422) // Oczekujemy statusu Unprocessable Entity
                 ->assertJsonValidationErrors(['first_name', 'last_name', 'emails.0.email']);
    }

    ## Testy pobierania użytkowników (GET /api/users, GET /api/users/{id})

    #[Test]
    public function it_can_get_a_list_of_users()
    {
        // Stwórz kilku użytkowników z mailami 
        $user1 = User::factory()->create(['first_name' => $this->faker->firstName, 'last_name' => $this->faker->lastName]);
        $user1->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);

        $user2 = User::factory()->create(['first_name' => $this->faker->firstName, 'last_name' => $this->faker->lastName]);
        $user2->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonFragment(['first_name' => $user1->first_name, 'last_name' => $user1->last_name])
                 ->assertJsonFragment(['first_name' => $user2->first_name, 'last_name' => $user2->last_name]);

        $response->assertJsonFragment(['email' => $user1->emailAddresses->first()->email]);
        $response->assertJsonFragment(['email' => $user2->emailAddresses->first()->email]);
    }

    #[Test]
    public function it_can_show_a_single_user()
    {
        $user = User::factory()->create(['first_name' => $this->faker->firstName, 'last_name' => $this->faker->lastName]);
        $primaryEmail = $this->faker->unique()->safeEmail;
        $secondaryEmail = $this->faker->unique()->safeEmail;
        $user->emailAddresses()->create(['email' => $primaryEmail]);
        $user->emailAddresses()->create(['email' => $secondaryEmail]);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'first_name' => $user->first_name,
                         'last_name' => $user->last_name,
                         'email_addresses' => [
                             ['email' => $primaryEmail],
                             ['email' => $secondaryEmail],
                         ]
                     ]
                 ]);
    }

    #[Test]
    public function it_returns_404_if_user_not_found_on_show()
    {
        $response = $this->getJson('/api/users/' . $this->faker->uuid()); // Losowe ID, które na pewno nie istnieje
        $response->assertStatus(404)
                 ->assertJson(['message' => 'User not found.']);
    }

    

    ## Testy aktualizacji użytkownika (PUT/PATCH /api/users/{id})

    #[Test]
    public function it_can_update_a_user_and_their_emails()
    {
        $user = User::factory()->create(); 
        $email1 = $user->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);
        $email2 = $user->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);

        $newFirstName = $this->faker->firstName;
        $newLastName = $this->faker->lastName;
        $newPhoneNumber = $this->faker->e164PhoneNumber();
        $updatedEmail = $this->faker->unique()->safeEmail;
        $newlyAddedEmail = $this->faker->unique()->safeEmail;

        $updateData = [
            'first_name' => $newFirstName,
            'last_name' => $newLastName,
            'phone_number' => $newPhoneNumber,
            'emails' => [
                // Aktualizacja istniejącego maila
                ['id' => $email1->id, 'email' => $updatedEmail],
                // Dodanie nowego maila
                ['email' => $newlyAddedEmail],
            ],
        ];

        $response = $this->putJson("/api/users/{$user->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'first_name' => $newFirstName,
                         'last_name' => $newLastName,
                         'phone_number' => $newPhoneNumber,
                     ]
                 ]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => $newFirstName, 'last_name' => $newLastName]);
        $this->assertDatabaseHas('email_addresses', ['id' => $email1->id, 'email' => $updatedEmail, 'user_id' => $user->id]);
        $this->assertDatabaseHas('email_addresses', ['email' => $newlyAddedEmail, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('email_addresses', ['id' => $email2->id]); // Stary drugi mail powinien zniknąć
        $this->assertCount(2, $user->fresh()->emailAddresses); // Powinny być teraz 2 maile
    }

    #[Test]
    public function it_returns_404_if_user_not_found_on_update()
    {
        $response = $this->putJson('/api/users/' . $this->faker->uuid(), ['first_name' => $this->faker->firstName]);
        $response->assertStatus(404)
                 ->assertJson(['message' => 'User not found.']);
    }

    #[Test]
    public function it_handles_email_updates_with_no_changes()
    {
        $user = User::factory()->create();
        $email = $user->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);

        $updateData = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'emails' => [
                ['id' => $email->id, 'email' => $email->email],
            ],
        ];

        $response = $this->putJson("/api/users/{$user->id}", $updateData);
        $response->assertStatus(200);
        $this->assertDatabaseHas('email_addresses', ['id' => $email->id, 'email' => $email->email]);
        $this->assertCount(1, $user->fresh()->emailAddresses);
    }




    ## Testy usuwania użytkownika (DELETE /api/users/{id})

    #[Test]
    public function it_can_delete_a_user()
    {
        $user = User::factory()->create();
        $user->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(204); // Oczekujemy statusu No Content

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('email_addresses', ['user_id' => $user->id]);
    }

    #[Test]
    public function it_returns_404_if_user_not_found_on_delete()
    {
        $response = $this->deleteJson('/api/users/' . $this->faker->uuid()); // Losowe ID, które na pewno nie istnieje
        $response->assertStatus(404)
                 ->assertJson(['message' => 'User not found.']);
    }



    ## Testy wysyłki maila (POST /api/users/{id}/send-welcome-email)

    #[Test]
    public function it_can_send_welcome_email_to_user_via_queue()
    {
        Mail::fake(); // Udawaj wysyłkę maili

        $user = User::factory()->create();
        $user->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);
        $user->emailAddresses()->create(['email' => $this->faker->unique()->safeEmail]);

        $response = $this->postJson("/api/users/{$user->id}/send-welcome-email");

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Welcome email has been queued for user ' . $user->full_name . '.']);

        Mail::assertQueued(WelcomeUserMail::class, 2);

        Mail::assertQueued(WelcomeUserMail::class, function ($mail) use ($user) {
            return $mail->user->id === $user->id;
        });
    }

    #[Test]
    public function it_returns_error_if_sending_email_to_non_existent_user()
    {
        $response = $this->postJson('/api/users/' . $this->faker->uuid() . '/send-welcome-email');
        $response->assertStatus(404)
                 ->assertJson(['message' => 'User not found.']);
    }

    #[Test]
    public function it_returns_error_if_sending_email_to_user_without_emails()
    {
        // Stwórz użytkownika bez maili
        $user = User::factory()->create(); 
        

        $response = $this->postJson("/api/users/{$user->id}/send-welcome-email");
        $response->assertStatus(400)
                 ->assertJsonFragment(['message' => 'User has no email addresses.']);
    }

}
