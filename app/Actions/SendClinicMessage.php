<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\OperationalChatEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class SendClinicMessage
{
    public const MAX_BODY_LENGTH = 2000;

    public const RATE_LIMIT_PER_MINUTE = 30;

    public function __construct(
        private OperationalChatEligibility $eligibility,
    ) {}

    public function handle(User $actor, Conversation $conversation, string $body): Message
    {
        Gate::forUser($actor)->authorize('messages.access');

        if (! $this->eligibility->canUseOperationalChat($actor)) {
            abort(403);
        }

        if ($conversation->clinic_id !== $actor->clinic_id) {
            abort(403);
        }

        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actor->id)
            ->where('clinic_id', $actor->clinic_id)
            ->exists();

        if (! $isParticipant) {
            abort(403);
        }

        $peer = $conversation->participants()
            ->with('user')
            ->where('user_id', '!=', $actor->id)
            ->where('clinic_id', $actor->clinic_id)
            ->first()
            ?->user;

        if ($peer === null || ! $this->eligibility->canChatWith($actor, $peer)) {
            throw ValidationException::withMessages([
                'body' => $peer !== null && ! $peer->active
                    ? 'Não é possível enviar mensagens para um usuário inativo.'
                    : $this->eligibility->rejectionMessage(),
            ]);
        }

        $normalized = trim(Str::of($body)->replace(["\r\n", "\r"], "\n")->toString());

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'body' => 'Digite uma mensagem.',
            ]);
        }

        if (mb_strlen($normalized) > self::MAX_BODY_LENGTH) {
            throw ValidationException::withMessages([
                'body' => 'A mensagem pode ter no máximo '.self::MAX_BODY_LENGTH.' caracteres.',
            ]);
        }

        $rateKey = 'clinic-message:'.$actor->clinic_id.':'.$actor->id;

        if (RateLimiter::tooManyAttempts($rateKey, self::RATE_LIMIT_PER_MINUTE)) {
            throw new TooManyRequestsHttpException(60, 'Muitas mensagens em pouco tempo. Aguarde um momento.');
        }

        RateLimiter::hit($rateKey, 60);

        return DB::transaction(function () use ($actor, $conversation, $normalized): Message {
            $message = new Message;
            $message->forceFill([
                'conversation_id' => $conversation->id,
                'clinic_id' => $actor->clinic_id,
                'sender_id' => $actor->id,
                'body' => $normalized,
            ])->save();

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $actor->id)
                ->where('clinic_id', $actor->clinic_id)
                ->update([
                    'last_read_at' => now(),
                    'last_read_message_id' => $message->id,
                ]);

            $conversation->touch();

            return $message->refresh()->load('sender');
        });
    }
}
