<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterSubscriptionConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class NewsletterSubscriptionController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return redirect()->back()->with('newsletter_status', 'success');
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
        ], [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator, 'newsletter')->withInput();
        }
        $validated = $validator->validated();

        $email = Str::lower(trim($validated['email']));
        $doubleOptIn = (bool) config('hotel.newsletter.double_opt_in');
        $rawVerificationToken = Str::random(64);
        $rawUnsubscribeToken = Str::random(64);
        $now = now();
        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber?->status === NewsletterSubscriber::STATUS_ACTIVE) {
            return redirect()->back()->with('newsletter_status', 'already');
        }

        $status = $doubleOptIn ? NewsletterSubscriber::STATUS_PENDING : NewsletterSubscriber::STATUS_ACTIVE;
        $attributes = [
            'email' => $email,
            'status' => $status,
            'source' => 'footer',
            'subscribed_at' => $subscriber?->subscribed_at ?? $now,
            'confirmed_at' => $doubleOptIn ? null : $now,
            'unsubscribed_at' => null,
            'verification_token' => hash('sha256', $rawVerificationToken),
            'unsubscribe_token' => hash('sha256', $rawUnsubscribeToken),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ];

        if ($subscriber) {
            $subscriber->update($attributes);
        } else {
            $subscriber = NewsletterSubscriber::query()->create($attributes);
        }

        if ($doubleOptIn) {
            $confirmationUrl = URL::temporarySignedRoute(
                'newsletter.confirm',
                now()->addDays(max(1, (int) config('hotel.newsletter.confirmation_ttl_days', 3))),
                ['token' => $rawVerificationToken],
            );

            try {
                Mail::to($subscriber->email)->queue(new NewsletterSubscriptionConfirmation($subscriber, $confirmationUrl));
            } catch (Throwable $exception) {
                Log::warning('Newsletter confirmation could not be queued.', [
                    'subscriber_id' => $subscriber->getKey(),
                    'exception' => $exception::class,
                ]);
            }

            return redirect()->back()->with('newsletter_status', 'pending');
        }

        return redirect()->back()->with('newsletter_status', 'success');
    }

    public function confirm(Request $request, string $token): View|Response
    {
        $subscriber = NewsletterSubscriber::findByToken('verification_token', $token);
        if (! $subscriber || $subscriber->status !== NewsletterSubscriber::STATUS_PENDING) {
            return response()->view('public.newsletter-result', $this->result('error'), 404);
        }

        $subscriber->update([
            'status' => NewsletterSubscriber::STATUS_ACTIVE,
            'confirmed_at' => now(),
            'verification_token' => null,
            'unsubscribed_at' => null,
        ]);

        return view('public.newsletter-result', $this->result('confirmed'));
    }

    public function unsubscribe(string $token): View|Response
    {
        $subscriber = NewsletterSubscriber::findByToken('unsubscribe_token', $token);
        if (! $subscriber) {
            return response()->view('public.newsletter-result', $this->result('error'), 404);
        }

        $subscriber->update([
            'status' => NewsletterSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        return view('public.newsletter-result', $this->result('unsubscribed'));
    }

    /** @return array{heading: string, message: string, state: string} */
    private function result(string $state): array
    {
        return match ($state) {
            'confirmed' => [
                'heading' => 'Subscription confirmed',
                'message' => 'Your Lodgix newsletter subscription is active. We’ll keep updates useful and occasional.',
                'state' => 'success',
            ],
            'unsubscribed' => [
                'heading' => 'You are unsubscribed',
                'message' => 'Your Lodgix newsletter subscription has been stopped. You can subscribe again any time from the footer.',
                'state' => 'success',
            ],
            default => [
                'heading' => 'Link unavailable',
                'message' => 'This newsletter link is invalid or has expired.',
                'state' => 'error',
            ],
        };
    }
}
