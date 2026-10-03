<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterAdminController extends Controller
{
    public function index(Request $request): View
    {
        $subscribers = NewsletterSubscriber::query()
            ->when(in_array($request->query('status'), NewsletterSubscriber::STATUSES, true), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
        $counts = NewsletterSubscriber::query()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');

        return view('newsletter.index', [
            'subscribers' => $subscribers,
            'counts' => $counts,
            'total' => $counts->sum(),
        ]);
    }

    public function updateStatus(Request $request, NewsletterSubscriber $newsletterSubscriber): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in([NewsletterSubscriber::STATUS_ACTIVE, NewsletterSubscriber::STATUS_UNSUBSCRIBED])]]);
        $status = $validated['status'];

        $newsletterSubscriber->update([
            'status' => $status,
            'confirmed_at' => $status === NewsletterSubscriber::STATUS_ACTIVE ? ($newsletterSubscriber->confirmed_at ?? now()) : $newsletterSubscriber->confirmed_at,
            'subscribed_at' => $status === NewsletterSubscriber::STATUS_ACTIVE ? ($newsletterSubscriber->subscribed_at ?? now()) : $newsletterSubscriber->subscribed_at,
            'unsubscribed_at' => $status === NewsletterSubscriber::STATUS_UNSUBSCRIBED ? now() : null,
            'verification_token' => null,
        ]);

        return redirect()->route('newsletter.index')->with('success', 'Subscriber status updated.');
    }

    public function destroy(NewsletterSubscriber $newsletterSubscriber): RedirectResponse
    {
        $newsletterSubscriber->delete();

        return redirect()->route('newsletter.index')->with('success', 'Subscriber deleted.');
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['email', 'status', 'subscribed_at', 'confirmed_at']);
            NewsletterSubscriber::query()->where('status', NewsletterSubscriber::STATUS_ACTIVE)->orderBy('id')->lazyById(500)->each(function (NewsletterSubscriber $subscriber) use ($handle): void {
                fputcsv($handle, [$subscriber->email, $subscriber->status, $subscriber->subscribed_at?->toIso8601String(), $subscriber->confirmed_at?->toIso8601String()]);
            });
            fclose($handle);
        }, 'lodgix-newsletter-active.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
