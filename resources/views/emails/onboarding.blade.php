@component('mail::message')
# Welcome to Madeena SSO

Hi {{ $user->name }},

Your account on **Madeena Single Sign-On (SSO)** has been set up and approved by the administrator.

To finalize your account setup and choose your secure password, please click the button below:

@component('mail::button', ['url' => $resetUrl, 'color' => 'primary'])
Set Up Your Password
@endcomponent

For security, this setup link will expire in 60 minutes.

If you did not request this account or if you believe this was sent in error, please contact your IT administrator.

Best regards,<br>
{{ config('app.name', 'Madeena SSO') }} Team
@endcomponent
