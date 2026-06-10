@component('mail::message')
# New User Registration

Hi Admin,

A new user has registered for a client application and is pending your approval:

**User Details:**
- **Name:** {{ $user->name }}
- **Email:** {{ $user->email }}
- **Target App:** {{ $client->name }}

Please review and approve this registration in the admin dashboard:

@component('mail::button', ['url' => $adminUrl, 'color' => 'primary'])
Review User Registration
@endcomponent

If you believe this registration is invalid or was created in error, you can reject or suspend the user's access from the admin panel.

Best regards,<br>
{{ config('app.name', 'Madeena SSO') }} Team
@endcomponent
