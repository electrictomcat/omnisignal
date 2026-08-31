<x-mail::message>
# Your licence portal link

Use the button below to view your OmniSignal licence keys, see which domains they're activated on, and free up a domain.

<x-mail::button :url="$url">
View my licences
</x-mail::button>

This link expires in {{ $expiresInMinutes }} minutes and can only be used from this email address.

If you didn't ask for it, you can ignore this message — nothing has changed on your account.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
