# Customer Auth API — Postman Manual Reference

Use this to **manually create / update** requests in Postman.  
**Workflow (step-by-step flows):** [Customer_Auth_API_Workflow.md](./Customer_Auth_API_Workflow.md)  
Base URL example: `http://127.0.0.1:8000` or `http://localhost/financeandchitfund/public`

**Test values**
| Key | Value |
|-----|-------|
| mobile | `9876543210` |
| password (default) | same as mobile |
| otp (test phone) | `123456` |
| mpin | `1234` |

**Headers (most requests)**
```
Accept: application/json
Content-Type: application/json
```

**There is no `session_token`.** Follow `next_step`. Bearer `token` is issued on set-mpin / verify-mpin.  
Send `fcm_token` on **login** (or first-time verify-otp) — it is stored immediately.  
**After set/verify MPIN:** copy `token` into `Authorization: Bearer {{token}}`.

---

## 1. Login (password)

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/login`

### Body parameters (JSON)

| Parameter | Required | Example | Notes |
|-----------|----------|---------|-------|
| username | Yes* | `9876543210` | Mobile **or** email (*or use email/mobile/phone) |
| email | No | `client@example.com` | Alternate username |
| mobile | No | `9876543210` | Alternate username |
| phone | No | `9876543210` | Alternate username |
| password | **Yes** | `9876543210` | Default = registered mobile |
| device_id | No | `postman-device-001` | |
| device_name | No | `Postman` | |
| device_model | No | `Desktop` | |
| fcm_token | No | `fcm_token_sample_abc123` | Firebase push token (or `device_token`) |
| device_token | No | `fcm_token_sample_abc123` | Alias of `fcm_token` |
| latitude | No | `13.0827` | |
| longitude | No | `80.2707` | |

Send `fcm_token` here. It is stored immediately on this login call.

### Body (copy to Postman)

```json
{
  "username": "9876543210",
  "password": "9876543210",
  "device_id": "postman-device-001",
  "device_name": "Postman",
  "device_model": "Desktop",
  "fcm_token": "fcm_token_sample_abc123"
}
```

### Demo response — success (MPIN not set)

```json
{
  "status": true,
  "message": "Credentials verified. Complete MPIN to continue.",
  "mpin_set": false,
  "next_step": "set_mpin",
  "fcm_stored": true,
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "9876543210@client.app"
  }
}
```

### Demo response — success (MPIN already set)

```json
{
  "status": true,
  "message": "Credentials verified. Complete MPIN to continue.",
  "mpin_set": true,
  "next_step": "verify_mpin",
  "fcm_stored": true,
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "client@example.com"
  }
}
```

### Demo response — error

```json
{
  "status": false,
  "message": "Invalid credentials"
}
```

---

## 2. Send OTP (first login)

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/send-otp`

### Body parameters

| Parameter | Required | Example | Notes |
|-----------|----------|---------|-------|
| mobile | Yes* | `9876543210` | Or use `phone` |
| phone | No | `9876543210` | Alias of mobile |
| purpose | No | `first_login` | `first_login` \| `forgot_password` \| `forgot_mpin` |

### Body

```json
{
  "mobile": "9876543210",
  "purpose": "first_login"
}
```

### Demo response

```json
{
  "status": true,
  "message": "OTP sent successfully (Test Mode)",
  "purpose": "first_login",
  "mpin_set": false,
  "expires_in": 300
}
```

---

## 3. Resend OTP

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/resend-otp`

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| mobile | Yes | `9876543210` |
| purpose | No | `first_login` |

### Body

```json
{
  "mobile": "9876543210",
  "purpose": "first_login"
}
```

### Demo response

```json
{
  "status": true,
  "message": "OTP sent successfully (Test Mode)",
  "purpose": "first_login",
  "mpin_set": false,
  "expires_in": 300
}
```

---

## 4. Verify OTP

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/verify-otp`

### Body parameters

| Parameter | Required | Example | Notes |
|-----------|----------|---------|-------|
| mobile | Yes | `9876543210` | |
| otp | Yes | `123456` | 6 digits |
| purpose | No | `first_login` | Same purpose used in send-otp |
| fcm_token | No | `fcm_token_sample_abc123` | Stored immediately on first-login verify |

### Body

```json
{
  "mobile": "9876543210",
  "otp": "123456",
  "purpose": "first_login",
  "device_id": "postman-device-001",
  "fcm_token": "fcm_token_sample_abc123"
}
```

### Demo response — first_login

```json
{
  "status": true,
  "message": "OTP verified successfully",
  "otp_verified": true,
  "mpin_set": false,
  "next_step": "set_mpin",
  "fcm_stored": true,
  "client": {
    "id": 12,
    "name": "Client 9876543210",
    "mobile": "9876543210",
    "email": "9876543210@client.app"
  },
  "default_credentials": {
    "username": "9876543210@client.app",
    "password_hint": "Default password is your registered mobile number"
  }
}
```

### Demo response — invalid OTP

```json
{
  "status": false,
  "message": "Invalid or expired OTP"
}
```

---

## 5. Set MPIN (first time)

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/set-mpin`

### Body parameters

| Parameter | Required | Example | Notes |
|-----------|----------|---------|-------|
| mobile | **Yes** | `9876543210` | |
| mpin | **Yes** | `1234` | 4–6 digits |
| mpin_confirmation | **Yes** | `1234` | Must match mpin |

### Body

```json
{
  "mobile": "9876543210",
  "mpin": "1234",
  "mpin_confirmation": "1234"
}
```

### Demo response — success (Bearer token)

```json
{
  "status": true,
  "message": "MPIN set successfully. Logged in.",
  "token_type": "Bearer",
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 45,
    "name": "Demo Client",
    "mobile": "9876543210",
    "phone": "9876543210",
    "email": "9876543210@client.app",
    "type": "client",
    "mpin_set": true
  },
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "9876543210@client.app"
  }
}
```

---

## 6. Verify MPIN

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/verify-mpin`

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| mpin | **Yes** | `1234` |

**Header:** `Authorization: Bearer {{token}}` (required when the app already has a token). Do not send `mobile`.

### Body

```json
{
  "mpin": "1234"
}
```

### Demo response — success

```json
{
  "status": true,
  "message": "Login successful",
  "token_type": "Bearer",
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 45,
    "name": "Demo Client",
    "mobile": "9876543210",
    "phone": "9876543210",
    "email": "client@example.com",
    "type": "client",
    "mpin_set": true
  },
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "client@example.com"
  }
}
```

### Demo response — error

```json
{
  "status": false,
  "message": "Invalid MPIN"
}
```

---

## 7. Forgot Password — Send OTP

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/forgot-password/send-otp`

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| mobile | Yes | `9876543210` |

### Body

```json
{
  "mobile": "9876543210"
}
```

### Demo response

```json
{
  "status": true,
  "message": "OTP sent successfully (Test Mode)",
  "purpose": "forgot_password",
  "mpin_set": true,
  "expires_in": 300
}
```

---

## 8. Forgot Password — Verify OTP

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/forgot-password/verify-otp`

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| mobile | Yes | `9876543210` |
| otp | Yes | `123456` |

### Body

```json
{
  "mobile": "9876543210",
  "otp": "123456"
}
```

### Demo response

```json
{
  "status": true,
  "message": "OTP verified. Set a new password.",
  "next_step": "reset_password",
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "client@example.com"
  }
}
```

---

## 9. Forgot Password — Reset

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/forgot-password/reset`  
(same as `/api/customer/auth/reset-password`)

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| mobile | **Yes** | `9876543210` |
| password | **Yes** | `NewPass@123` |
| password_confirmation | **Yes** | `NewPass@123` |

### Body

```json
{
  "mobile": "9876543210",
  "password": "NewPass@123",
  "password_confirmation": "NewPass@123"
}
```

### Demo response

```json
{
  "status": true,
  "message": "Password reset successfully. Complete MPIN to continue.",
  "mpin_set": true,
  "next_step": "verify_mpin",
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "client@example.com"
  }
}
```

---

## 10. Forgot MPIN — Send OTP

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/forgot-mpin/send-otp`

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| mobile | Yes | `9876543210` |

### Body

```json
{
  "mobile": "9876543210"
}
```

### Demo response

```json
{
  "status": true,
  "message": "OTP sent successfully (Test Mode)",
  "purpose": "forgot_mpin",
  "mpin_set": true,
  "expires_in": 300
}
```

---

## 11. Forgot MPIN — Verify OTP (resets MPIN in this step)

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/forgot-mpin/verify-otp`

OTP verify **and** new MPIN in one call. Separate `/forgot-mpin/reset` is not required.

### Body parameters

| Parameter | Required | Example | Notes |
|-----------|----------|---------|-------|
| mobile | Yes | `9876543210` | Registered mobile |
| otp | Yes | `123456` | 6-digit OTP |
| mpin | **Yes** | `1234` | New MPIN (4–6 digits) |
| mpin_confirmation | **Yes** | `1234` | Must match `mpin` |
| fcm_token | No | `fcm_token_sample_abc123` | Stored on login success |
| device_id | No | `postman-device-001` | |
| device_name | No | `Postman` | |
| device_model | No | `Desktop` | |

### Body

```json
{
  "mobile": "9876543210",
  "otp": "123456",
  "mpin": "1234",
  "mpin_confirmation": "1234",
  "fcm_token": "fcm_token_sample_abc123",
  "device_id": "postman-device-001"
}
```

### Demo response

```json
{
  "status": true,
  "message": "MPIN reset successfully. Logged in.",
  "token_type": "Bearer",
  "token": "2|yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy",
  "user": {
    "id": 45,
    "name": "Demo Client",
    "mobile": "9876543210",
    "phone": "9876543210",
    "email": "client@example.com",
    "type": "client",
    "mpin_set": true
  },
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "client@example.com"
  }
}
```

---

## 12. Forgot MPIN — Reset (legacy, optional)

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/forgot-mpin/reset`

Use only if verify-otp was called **without** `mpin`. Prefer section 11.

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| mobile | **Yes** | `9876543210` |
| mpin | **Yes** | `1234` |
| mpin_confirmation | **Yes** | `1234` |

### Body

```json
{
  "mobile": "9876543210",
  "mpin": "1234",
  "mpin_confirmation": "1234"
}
```

### Demo response

```json
{
  "status": true,
  "message": "MPIN reset successfully. Logged in.",
  "token_type": "Bearer",
  "token": "2|yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy",
  "user": {
    "id": 45,
    "name": "Demo Client",
    "mobile": "9876543210",
    "phone": "9876543210",
    "email": "client@example.com",
    "type": "client",
    "mpin_set": true
  },
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "client@example.com"
  }
}
```

---

## 13. Me (profile)

**Method:** `GET`  
**URL:** `{{base_url}}/api/customer/auth/me`

### Header parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| Authorization | **Yes** | `Bearer 1|xxxxxxxx` |

No body.

### Demo response

```json
{
  "status": true,
  "data": {
    "user": {
      "id": 45,
      "name": "Demo Client",
      "phone": "9876543210",
      "email": "client@example.com",
      "username_hint": "client@example.com"
    },
    "client": {
      "id": 12,
      "name": "Demo Client",
      "mobile": "9876543210",
      "email": "client@example.com",
      "mpin_set": true,
      "status": "active"
    }
  }
}
```

---

## 14. Change MPIN

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/change-mpin`

### Header parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| Authorization | **Yes** | `Bearer 1|xxxxxxxx` |

### Body parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| current_mpin | **Yes** | `1234` |
| mpin | **Yes** | `5678` |
| mpin_confirmation | **Yes** | `5678` |

### Body

```json
{
  "current_mpin": "1234",
  "mpin": "5678",
  "mpin_confirmation": "5678"
}
```

### Demo response

```json
{
  "status": true,
  "message": "MPIN changed successfully"
}
```

---

## 15. Logout

**Method:** `POST`  
**URL:** `{{base_url}}/api/customer/auth/logout`  
(legacy same API: `/api/users/logout`)

Expires the FCM token for this device (`users.fcm_token`, `clients.fcm_token`, `user_devices.device_token`) so push notifications stop after logout.

### Header parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| Authorization | **Yes** | `Bearer 1|xxxxxxxx` |

### Body parameters (optional)

| Parameter | Required | Example |
|-----------|----------|---------|
| device_id | No | `postman-device-001` | Same id used at login |

### Body (optional)

```json
{
  "device_id": "postman-device-001"
}
```

### Demo response — success

```json
{
  "status": true,
  "message": "Logged out successfully"
}
```

### Demo response — error

```json
{
  "status": false,
  "message": "Unable to logout. Token not found or invalid."
}
```

---

## 13. Register / Update FCM Device Token

**Method:** `POST`  
**URL:** `{{base_url}}/api/users/user/device`  
(or aliases: `{{base_url}}/api/users/user/fcm-token`, `{{base_url}}/api/users/fcm-token/update`, `{{base_url}}/api/users/fcm-token`)

### Header parameters

| Parameter | Required | Example |
|-----------|----------|---------|
| Authorization | **Yes** | `Bearer 1|xxxxxxxx` |
| Content-Type | **Yes** | `application/json` |
| X-App-Type | No | `client` |

### Body parameters (JSON)

| Parameter | Required | Example | Notes |
|-----------|----------|---------|-------|
| fcm_token | Yes* | `fcm_token_sample_abc123...` | FCM / Push token (*or `device_token`) |
| device_token | Yes* | `fcm_token_sample_abc123...` | Alternate field name for FCM token |
| device_id | No | `android-device-123` | Optional device identifier |
| device_name | No | `Google Pixel 7` | Optional device name |
| device_model | No | `Pixel 7 Pro` | Optional device model |

### Body (copy to Postman)

```json
{
  "fcm_token": "fcm_token_sample_abc123_xyz789_device_token",
  "device_id": "android-device-123",
  "device_name": "Customer Android Phone",
  "device_model": "Pixel 7"
}
```

### Demo response — success

```json
{
  "status": true,
  "message": "FCM device token saved successfully",
  "data": {
    "user_id": 12,
    "client_id": 12,
    "fcm_token": "fcm_token_sample_abc123_xyz789_device_token",
    "device_id": "android-device-123",
    "device_model": "Pixel 7"
  }
}
```

---

## Quick flow (manual Postman)

1. **Login** with `fcm_token` → FCM stored immediately; follow `next_step` (`set_mpin` or `verify_mpin`)
2. **Set MPIN** or **Verify MPIN** → copy Bearer `token`
3. **Me** / **Change MPIN** / **Logout** → Header: `Authorization: Bearer {{token}}`

**Forgot password:** Send OTP → Verify OTP → Reset → Set/Verify MPIN → Bearer `token`  
**Forgot MPIN:** Send OTP → Verify OTP (include `mpin` + `mpin_confirmation`) → Bearer `token`

**FCM token storage**
| When | What happens | Where |
|------|----------------|-------|
| `POST /login` or first-time `POST /verify-otp` | Store FCM immediately | `users.fcm_token`, `clients.fcm_token`, `user_devices.device_token` |
| `set-mpin` / `verify-mpin` | Issue Bearer token | — |
| Logout | Expire / clear token | Same three columns (`device_token` + `logout_at` on that device) |

