# Email Campaign API Documentation

This documentation provides details on how to use the Email Campaign API to create and manage email campaigns.

## Google Account Integration

To send emails from a user's own Google account, you must first connect their account using the OAuth 2.0 flow.

### Required Configuration

Before you begin, ensure you have correctly configured the following in your `.env` file:

- `GOOGLE_CLIENT_ID`: Your Google application client ID.
- `GOOGLE_CLIENT_SECRET`: Your Google application client secret.
- `GOOGLE_REDIRECT_URI`: The callback URL. This **must** be set to `http://<your-app-url>/email-provider/google/callback`.
- `FRONTEND_URL`: The URL of your frontend application where users will be redirected after connecting their account (e.g., `http://localhost:3000/settings`).

### How to Connect a Google Account

The connection is established using a browser-based redirect flow, not a direct API call.

1.  **Check Connection Status:**
    First, call the `GET /api/user/email-provider` endpoint to see if an account is already connected.

2.  **Initiate Authorization Flow:**
    If no account is connected, provide a link or button in your frontend that directs the user's browser to the following URL:
    `http://<your-app-url>/email-provider/google/redirect`

3.  **User Consent and Redirect:**
    The user will be taken to Google's consent screen. After they grant permission, they will be redirected back to your application at your configured `GOOGLE_REDIRECT_URI`. The backend handles the token exchange and storage automatically.

4.  **Redirect to Frontend:**
    After the connection is successful, the user will be redirected to the `FRONTEND_URL` you have configured.

5.  **Verify Connection:**
    You can now call `GET /api/user/email-provider` again to confirm the connection and update your UI.

---

## Manage Email Provider Connection

### Check Connection Status

Retrieves the connected email provider for the authenticated user.

- **URL:** `/api/user/email-provider`
- **Method:** `GET`
- **Auth required:** Yes

#### Success Response

- **Code:** `200 OK`
- **Content:** The email provider object.

```json
{
    "id": 1,
    "user_id": 123,
    "provider": "google",
    "created_at": "2023-10-27T10:00:00.000000Z",
    "updated_at": "2023-10-27T10:00:00.000000Z"
}
```

#### Error Response (Not Connected)

- **Code:** `404 NOT FOUND`

```json
{
    "message": "No email provider connected"
}
```

### Disconnect Email Provider

Disconnects the authenticated user's email provider.

- **URL:** `/api/user/email-provider`
- **Method:** `DELETE`
- **Auth required:** Yes

#### Success Response

- **Code:** `200 OK`

```json
{
    "message": "Email provider disconnected successfully"
}
```

---

## Create Email Campaign

Creates a new email campaign.

- **URL:** `/api/email-campaigns`
- **Method:** `POST`
- **Auth required:** Yes

### Parameters

| Name            | Type    | Description                                                                                                                              |
| --------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `name`          | string  | The name of the campaign.                                                                                                                |
| `subject`       | string  | The subject of the email.                                                                                                                |
| `sender`        | integer | **(Breaking Change)** The ID of the user who is sending the campaign. This user must have a connected email provider.                       |
| `audience`      | array   | An array of user IDs to send the campaign to.                                                                                            |
| `content`       | string  | The HTML content of the email. Supports personalization with `{{first_name}}` and `{{company}}`.                                           |
| `schedule`      | string  | Determines when the campaign should be sent. Can be `now` or `later`.                                                                    |
| `schedule_time` | string  | The scheduled time for the campaign to be sent. Required if `schedule` is `later`. Should be in `YYYY-MM-DDTHH:MM` format and in the future. |

### Example Request (Send Now)

```json
{
    "name": "Welcome Campaign",
    "subject": "Welcome to our platform!",
     "sender": 123,
    "audience": ["1", "2", "3"],
    "content": "<h1>Hi {{first_name}}!</h1><p>Welcome to {{company}}.</p>",
    "schedule": "now"
}
```

### Example Request (Schedule for Later)

```json
{
    "name": "Scheduled Campaign",
    "subject": "This is a scheduled email",
    "sender": 123,
    "audience": ["1", "2", "3"],
    "content": "<h1>Hi {{first_name}}!</h1><p>This email was scheduled for later.</p>",
    "schedule": "later",
    "schedule_time": "2025-12-25T10:00:00"
}
```

### Success Response

- **Code:** `201 CREATED`
- **Content:** The created email campaign object.

```json
{
    "id": 1,
    "name": "Welcome Campaign",
    "subject": "Welcome to our platform!",
    "sender": 123,
    "audience": ["1", "2", "3"],
    "content": "<h1>Hi {{first_name}}!</h1><p>Welcome to {{company}}.</p>",
    "schedule": "now",
    "schedule_time": null,
    "created_at": "2023-10-27T10:00:00.000000Z",
    "updated_at": "2023-10-27T10:00:00.000000Z"
}
```
