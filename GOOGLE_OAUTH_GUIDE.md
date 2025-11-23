# Google OAuth Implementation Guide

## Overview
This application now supports Google OAuth authentication using Laravel Socialite. The OAuth credentials are stored in the database settings table for easy management through the admin panel.

## Setup Instructions

### 1. Create Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google+ API
4. Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"
5. Configure the OAuth consent screen
6. Select "Web application" as the application type
7. Add authorized redirect URIs:
   - For local development: `http://localhost:8000/auth/google/callback`
   - For production: `https://yourdomain.com/auth/google/callback`
8. Copy the Client ID and Client Secret

### 2. Configure Settings in Database

You can set the Google OAuth credentials in three ways:

#### Option A: Through Filament Admin Panel
1. Navigate to Settings in your Filament admin panel
2. Find the Google OAuth settings
3. Enter your:
   - Google Client ID
   - Google Client Secret
   - Google Redirect URL (e.g., `http://localhost:8000/auth/google/callback`)

#### Option B: Using Tinker
```bash
php artisan tinker
```

```php
$service = app(\App\Services\SettingService::class);
$service->set(\App\Enums\SettingKey::GOOGLE_CLIENT_ID, 'your-client-id');
$service->set(\App\Enums\SettingKey::GOOGLE_CLIENT_SECRET, 'your-client-secret');
$service->set(\App\Enums\SettingKey::GOOGLE_REDIRECT_URL, 'http://localhost:8000/auth/google/callback');
```

#### Option C: Using Environment Variables (Fallback)
Add to your `.env` file:
```env
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URL=http://localhost:8000/auth/google/callback
```

**Note:** Database settings take precedence over environment variables.

### 3. Database Migration
The migration has already been run to add OAuth fields to the users table:
- `google_id` - Stores the Google user ID
- `avatar` - Stores the user's Google profile picture URL

### 4. Test the Implementation
1. Visit the login or register page
2. Click "Continue with Google" button
3. Authorize the application
4. You'll be redirected back and logged in automatically

## Features

### User Authentication Flow
1. **New Users**: When a user logs in with Google for the first time:
   - A new user account is created
   - Email is marked as verified
   - Google ID and avatar are stored
   - Random password is generated (user can reset if needed)

2. **Existing Users**: When a user with an existing email logs in with Google:
   - Their account is linked to their Google ID
   - Avatar is updated
   - They are logged in automatically

### UI/UX Features
- ✅ RTL support for Arabic language
- ✅ Dark mode support
- ✅ Responsive design with mobile-first approach
- ✅ Google branding colors and logo
- ✅ Loading states and error handling
- ✅ Smooth transitions and animations

## Routes

```php
GET  /auth/google           - Redirect to Google OAuth
GET  /auth/google/callback  - Handle Google OAuth callback
```

## Files Modified/Created

### Backend
- `app/Enums/SettingKey.php` - Added Google OAuth setting keys
- `app/Services/SettingService.php` - Already supports the new settings
- `app/Http/Controllers/Auth/GoogleAuthController.php` - New OAuth controller
- `app/Models/User.php` - Added fillable fields for OAuth
- `config/services.php` - Google OAuth configuration
- `routes/auth.php` - OAuth routes
- `database/migrations/xxxx_add_oauth_fields_to_users_table.php` - OAuth fields migration

### Frontend
- `resources/js/themes/default/pages/Login.tsx` - Added Google login button
- `resources/js/themes/default/pages/Register.tsx` - Added Google login button

## Translation Keys
Add these translation keys to your language files:

```javascript
{
  "orContinueWith": "Or continue with",
  "continueWithGoogle": "Continue with Google"
}
```

## Security Considerations

1. **HTTPS Required**: In production, always use HTTPS for OAuth callbacks
2. **Environment-based URLs**: The redirect URL should match your environment
3. **Error Handling**: Failed OAuth attempts redirect to login with an error message
4. **Database Settings**: Store sensitive credentials in the database with proper encryption
5. **Password Generation**: OAuth users get random passwords they can reset if needed

## Troubleshooting

### "Unable to login with Google" Error
- Verify Google Client ID and Client Secret are correct
- Check that the redirect URL matches exactly (including protocol)
- Ensure Google+ API is enabled in Google Cloud Console
- Verify the OAuth consent screen is configured

### Users Can't Link Google Account
- Check that the email addresses match
- Verify the users table has `google_id` and `avatar` columns
- Check application logs for detailed error messages

## Testing

To test the OAuth flow:

1. **Local Testing**: Use `http://localhost:8000/auth/google/callback`
2. **Production Testing**: Update the callback URL in Google Console
3. **Multiple Environments**: Create separate OAuth credentials for dev/staging/production

## Future Enhancements

Consider adding:
- [ ] More OAuth providers (Facebook, Twitter, GitHub)
- [ ] Account linking/unlinking UI
- [ ] OAuth provider management in user profile
- [ ] Admin panel for viewing OAuth statistics
