<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeUserMail;
use App\Models\EmailAddress;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $users = User::with('emailAddresses')->get();
        return response()->json(['data' => $users]);
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:15',
            'emails' => 'required|array|min:1',
            'emails.*.email' => 'required|email|max:255',
        ]);
        try {
            DB::beginTransaction();

            $user = User::create([
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'phone_number' => $validatedData['phone_number'] ?? null,
            ]);

            foreach ($validatedData['emails'] as $emailData) {
                $user->emailAddresses()->create([
                    'email' => $emailData['email'],
                ]);
            }
            DB::commit();

            return response()->json(['data'=> $user->load('emailAddresses')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create user.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified user.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $user = User::with('emailAddresses')->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        return response()->json(['data' => $user]);
    }

    /**
    * Update the specified user in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  string  $id
    * @return \Illuminate\Http\JsonResponse
    */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Używamy Validator::make dla metody update, aby mieć lepszą kontrolę nad walidacją unikalności
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:15',
            'emails' => 'required|array|min:1',
            'emails.*.id' => 'nullable|exists:email_addresses,id', // ID musi istnieć, jeśli podane
            'emails.*.email' => 'required|email|max:255', // Podstawowa walidacja formatu i długości
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // --- Ręczna walidacja unikalności adresów e-mail ---
        $emails = $request->input('emails');
        $validationErrors = [];

        foreach ($emails as $index => $emailData) {
            $email = $emailData['email'];
            $emailId = $emailData['id'] ?? null;

            // Sprawdzamy, czy email już istnieje i nie jest to ten sam rekord, który aktualizujemy
            $query = EmailAddress::where('email', $email);
            if ($emailId) {
                $query->where('id', '!=', $emailId);
            }
            if ($query->exists()) {
                $validationErrors["emails.{$index}.email"] = ["Podany adres e-mail '{$email}' jest już zajęty."];
            }
        }

        if (!empty($validationErrors)) {
            return response()->json(['errors' => $validationErrors], 422);
        }
        // --- Koniec ręcznej walidacji unikalności ---


        try {
            return DB::transaction(function () use ($request, $user) {
                // Używamy $request->only() po walidacji, by pobrać tylko odpowiednie dane
                $user->update($request->only('first_name', 'last_name', 'phone_number'));

                $incomingEmailIds = collect($request->input('emails'))->pluck('id')->filter()->all();

                // Usuń maile, które nie zostały przesłane w żądaniu
                $user->emailAddresses()->whereNotIn('id', $incomingEmailIds)->delete();

                foreach ($request->input('emails') as $emailData) {
                    if (isset($emailData['id'])) {
                        // Aktualizuj istniejący adres e-mail
                        EmailAddress::where('id', $emailData['id'])
                            ->where('user_id', $user->id)
                            ->update(['email' => $emailData['email']]);
                    } else {
                        // Utwórz nowy adres e-mail
                        $user->emailAddresses()->create(['email' => $emailData['email']]);
                    }
                }

                return response()->json(['data' => $user->load('emailAddresses')]);
            });
        } catch (\Exception $e) {
            Log::error("Failed to update user: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update user.', 'error' => $e->getMessage()], 500);
        }
    }




    /**
     * Remove the specified user from storage.
     * 
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        try {
            DB::beginTransaction(); //  Begin transaction

            $user->delete(); // Laravel will automatically delete related email addresses due to onDelete('cascade')

            DB::commit(); // Commit transaction

            return response()->json(null, 204); // No content
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            // Log error for debugging
            Log::error("Error deleting user {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete user.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send welcome email to the user at all their addresses.
     */
    public function sendWelcomeEmail(string $id): JsonResponse
    {
        $user = User::with('emailAddresses')->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->emailAddresses->isEmpty()) {
            return response()->json(['message' => 'User has no email addresses.'], 400);
        }

        try {
            foreach ($user->emailAddresses as $emailAddress) {
                // Use the queue() method to add the email to the queue
                Mail::to($emailAddress->email)->queue(new WelcomeUserMail($user));
            }

            return response()->json(['message' => 'Welcome email has been queued for user ' . $user->full_name . '.'], 200);
        } catch (\Exception $e) {
            // Log error for debugging
            Log::error("Error queuing welcome email for user {$user->id}: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while queuing the email.', 'error' => $e->getMessage()], 500);
        }
    }
}
