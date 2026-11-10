# Customer App API Endpoints & Payloads

Base URL: `/api/customer/auth`  
Example: `http://localhost/financeandchitfund/public/api/customer/auth`

**Headers (all requests)**
- `Accept: application/json`
- `Content-Type: application/json` (or `multipart/form-data` for register photos)

**After set-mpin / verify-mpin / forgot-mpin verify-otp**
- `Authorization: Bearer <token>`  
  Copy the **full** token including the number and `|` (example: `6|xxxxxxxxxxxxxxxx`).

There is **no `session_token`**. Follow `next_step`. FCM is stored on **login** or first-time **verify-otp**. Bearer is issued only on MPIN next step.

**Test values**
- mobile: `9876543210`
- password (default): same as mobile
- otp (test phone): `123456`
- mpin: `1234`

---

## App flow (follow `next_step`)

| `next_step` | Call next | Bearer? |
|-------------|-----------|---------|
| `set_mpin` | `POST /set-mpin` | Yes — this call returns `token` |
| `verify_mpin` | `POST /verify-mpin` | Yes — this call returns `token` |
| `reset_password` | `POST /forgot-password/reset` | No — then set/verify MPIN |
| *(forgot MPIN with mpin in verify-otp)* | Done | Yes — response already has `token` |

**Register:** GET register-dropdowns (or locations) → POST register with **all Add Client fields** → follow `next_step`  
**Existing user:** Login (`fcm_token`) → Set/Verify MPIN → Bearer → Me / Logout  
**New mobile:** Send OTP → Verify OTP (`fcm_token`) → Set MPIN → Bearer  
**Forgot password:** Send OTP → Verify OTP → Reset password → Set/Verify MPIN → Bearer  
**Forgot MPIN:** Send OTP → Verify OTP + new MPIN → Bearer  

---

## 1. Locations (for register `location_id`)

Public. No Bearer token. Call this first, show the list, then send the chosen `id` as `location_id` in register.

**Endpoint:** `GET /api/customer/locations`  
Aliases: `GET /api/customer/auth/locations`, `GET /api/app/locations`

**Query Parameters:**
- `search` (optional, string: name / city / state / pincode)
- `city` (optional, string)

**Request Payload:** None

**Response:**
```json
{
  "status": true,
  "success": true,
  "message": "Locations fetched successfully",
  "data": [
    {
      "id": 1,
      "name": "Anna Nagar",
      "city": "Chennai",
      "state": "Tamil Nadu",
      "pincode": "600040"
    },
    {
      "id": 2,
      "name": "T Nagar",
      "city": "Chennai",
      "state": "Tamil Nadu",
      "pincode": "600017"
    }
  ]
}
```

Use `data[].id` as `location_id` in **POST /register**. Prefer **GET /register-dropdowns** for every Add Client select list in one call.

---

## 1b. Register dropdowns (Add Client selects)

Public. No Bearer token. Same options as **Admin → Add Client**.

**Endpoint:** `GET /api/customer/auth/register-dropdowns`  
Alias: `GET /api/customer/register-dropdowns`

**Response `data` keys:** `locations`, `genders`, `marital_statuses`, `account_types`, `nominee_relationships`, `guarantor_relationships`, `referral_relationships`, `employment_types`, `collection_days`

Each option is `{ "value": "...", "label": "..." }` except `locations` which is `{ id, name, city, state, pincode }`.

Use `value` in the register payload. Use location `id` as `location_id`.

---

## 2. Register (new customer)

Creates client as **pending** until admin KYC verify. Then follow `next_step` for MPIN.

Same fields as **Admin → Add Client**. Use `multipart/form-data` when sending photos.

**Endpoint:** `POST /register`

**Required (same as Add Client)**
- Personal: `name`, `phone`, `location_id`
- KYC / bank: `aadhar_number`, `pan_number`, `account_number`, `ifsc_code`, `branch_name`
- Photos: `selfie_photo`, `aadhar_photo_front`, `aadhar_photo_back` (file or base64)

**Optional (send all of these from the Add Client form)**
- Personal: `email`, `alternate_phone`, `date_of_birth` (`DD-MM-YYYY` or `Y-m-d`), `gender`, `marital_status`, `address`, `city`, `state`, `pincode`, `collection_day`
- Bank: `account_holder`, `bank_name`, `account_type` (`savings` | `current`)
- Nominee 1: `nominee1_name`, `nominee1_relationship`, `nominee1_mobile`
- Nominee 2: `nominee2_name`, `nominee2_relationship`, `nominee2_mobile`
- Guarantor: `guarantor_name` (or `guarantorName`), `guarantor_phone`, `guarantor_relationship`
- Referral: `referral_name`, `referral_phone`, `referral_relationship`
- Employment: `employment_type` (`salaried` | `business`), `company_name`, `monthly_salary`, `payslip`, `business_name`, `monthly_income` (or `monthly_turnover`), `business_document`
- Extra docs: `pan_photo`, `bank_statement`
- Auth: `password` + `password_confirmation` (if omitted, default password = mobile)

**Request Payload:** (`multipart/form-data` if sending photo files)

```json
{
  "name": "Demo Client",
  "phone": "9876543210",
  "email": "demo@example.com",
  "alternate_phone": "9876543211",
  "date_of_birth": "15-08-1995",
  "gender": "male",
  "marital_status": "married",
  "location_id": 1,
  "address": "12, 2nd Street, Anna Nagar",
  "city": "Chennai",
  "state": "Tamil Nadu",
  "pincode": "600040",
  "aadhar_number": "123456789012",
  "pan_number": "ABCDE1234F",
  "account_holder": "Demo Client",
  "account_number": "12345678901",
  "bank_name": "HDFC Bank",
  "ifsc_code": "HDFC0001234",
  "branch_name": "Anna Nagar",
  "account_type": "savings",
  "nominee1_name": "Jane Doe",
  "nominee1_relationship": "spouse",
  "nominee1_mobile": "9876500001",
  "nominee2_name": "John Senior",
  "nominee2_relationship": "father",
  "nominee2_mobile": "9876500002",
  "guarantor_name": "Ravi Kumar",
  "guarantor_phone": "9876500003",
  "guarantor_relationship": "Friend",
  "referral_name": "Priya",
  "referral_phone": "9876500004",
  "referral_relationship": "Associate",
  "employment_type": "salaried",
  "company_name": "Acme Pvt Ltd",
  "monthly_salary": 35000,
  "password": "9876543210",
  "password_confirmation": "9876543210",
  "selfie_photo": "<file or base64>",
  "aadhar_photo_front": "<file or base64>",
  "aadhar_photo_back": "<file or base64>",
  "pan_photo": "<optional file or base64>",
  "bank_statement": "<optional file or base64>",
  "payslip": "<optional file or base64>"
}
```

For `employment_type` = `business`, send `business_name`, `monthly_income`, `business_document` instead of company/salary/payslip.

Aliases: `mobile` / `client_phone` → `phone`, `client_name` → `name`, `aadhaar_number` → `aadhar_number`, `account_holder_name` → `account_holder`.  
Guarantor/referral also accept admin camelCase: `guarantorName`, `guarantorPhone`, `guarantorRelationship`, `referralName`, `referralPhone`, `referralRelationship`.  
`location_id` is required — get it from **GET /api/customer/auth/register-dropdowns** or **GET /api/customer/locations**.

**Response:**
```json
{
  "status": true,
  "message": "Customer registered successfully. Registration is submitted for admin KYC verification.",
  "next_step": "set_mpin",
  "client_id": 12,
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "demo@example.com",
    "status": "pending",
    "kyc_verified": false,
    "is_active": false
  },
  "credentials": {
    "email": "demo@example.com",
    "mobile": "9876543210",
    "username_hint": "You can log in using either your registered email or mobile number as username.",
    "password_created": true
  }
}
```

---

## 2. Create / Set Password

**Endpoint:** `POST /create-password`  
Alias: `POST /set-password`

**Request Payload:**
```json
{
  "mobile": "9876543210",
  "password": "NewPass@123",
  "password_confirmation": "NewPass@123"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Password created successfully. You can now log in using your registered email or mobile number.",
  "mpin_set": false,
  "next_step": "set_mpin",
  "credentials": {
    "email": "demo@example.com",
    "mobile": "9876543210",
    "username_hint": "Use either your email or mobile number as username with your new password."
  },
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210"
  }
}
```

---

## 3. Login (password)

Stores `fcm_token` immediately. Does **not** return Bearer. Follow `next_step`.

**Endpoint:** `POST /login`

**Request Payload:**
```json
{
  "username": "9876543210",
  "password": "9876543210",
  "device_id": "android-device-001",
  "device_name": "Pixel",
  "device_model": "Pixel 7",
  "fcm_token": "YOUR_FCM_TOKEN"
}
```

`username` can be mobile **or** email. Alternate fields: `email`, `mobile`, `phone`. `device_token` is an alias of `fcm_token`.

**Response (MPIN not set):**
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
    "email": "9876543210@client.app",
    "status": "pending"
  }
}
```

**Response (MPIN already set):** same body with `"mpin_set": true`, `"next_step": "verify_mpin"`.

**Error:**
```json
{
  "status": false,
  "message": "Invalid credentials"
}
```

---

## 4. Send OTP (first login / recovery)

**Endpoint:** `POST /send-otp`  
Aliases: `POST /resend-otp`, `POST /forgot-password/send-otp`, `POST /forgot-mpin/send-otp`

**Request Payload:**
```json
{
  "mobile": "9876543210",
  "purpose": "first_login"
}
```

`purpose`: `first_login` | `forgot_password` | `forgot_mpin`  
Forgot-password / forgot-mpin aliases set purpose automatically.

**Response:**
```json
{
  "status": true,
  "message": "OTP sent successfully (Test Mode)",
  "purpose": "first_login",
  "mpin_set": false,
  "expires_in": 300
}
```

Live number message: `"OTP sent successfully to your mobile number"`.

---

## 5. Verify OTP (first login)

Stores `fcm_token` immediately. Does **not** return Bearer. Follow `next_step`.

**Endpoint:** `POST /verify-otp`

**Request Payload:**
```json
{
  "mobile": "9876543210",
  "otp": "123456",
  "purpose": "first_login",
  "device_id": "android-device-001",
  "fcm_token": "YOUR_FCM_TOKEN"
}
```

**Response:**
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

OTP-created clients stay `status = pending` until admin KYC verify.

---

## 6. Set MPIN (first time) → Bearer token

Call when `next_step` is `set_mpin`.

**Endpoint:** `POST /set-mpin`

**Request Payload:**
```json
{
  "mobile": "9876543210",
  "mpin": "1234",
  "mpin_confirmation": "1234"
}
```

**Response:**
```json
{
  "status": true,
  "message": "MPIN set successfully. Logged in.",
  "token_type": "Bearer",
  "token": "6|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "authorization": "Bearer 6|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 45,
    "name": "Demo Client",
    "mobile": "9876543210",
    "phone": "9876543210",
    "email": "9876543210@client.app",
    "type": "client",
    "mpin_set": true,
    "status": "pending"
  },
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210",
    "email": "9876543210@client.app",
    "status": "pending"
  }
}
```

Use:

```
Authorization: Bearer 6|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## 7. Verify MPIN (Bearer token) 

App already has a token (from previous login). Send that token. **Do not send mobile.**

**Endpoint:** `POST /verify-mpin`

**Headers**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer 8|xxxxxxxxxxxxxxxx
```

Copy the **full** stored token (`id|plaintext`).

**Request Payload:**
```json
{
  "mpin": "1234"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Login successful",
  "token_type": "Bearer",
  "token": "8|xxxxxxxxxxxxxxxx",
  "authorization": "Bearer 8|xxxxxxxxxxxxxxxx",
  "user": { "id": 45, "name": "Demo Client", "mobile": "9876543210", "type": "client", "mpin_set": true },
  "client": { "id": 12, "name": "Demo Client", "mobile": "9876543210" }
}
```

**Error (token missing):**
```json
{
  "status": false,
  "message": "Send Authorization: Bearer {token}. Mobile is not required when the token is sent.",
  "next_step": "login"
}
```

**Error (wrong MPIN):**
```json
{
  "status": false,
  "message": "Invalid MPIN"
}
```

First-time after password login (no token yet) still works with pending session + `mobile` + `mpin`. After that, always use Bearer.

---

## 8. Forgot Password — Verify OTP

**Endpoint:** `POST /forgot-password/verify-otp`

**Request Payload:**
```json
{
  "mobile": "9876543210",
  "otp": "123456"
}
```

**Response:**
```json
{
  "status": true,
  "message": "OTP verified. Set a new password.",
  "next_step": "reset_password",
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210"
  }
}
```

---

## 9. Forgot Password — Reset

Then follow `next_step` → set-mpin or verify-mpin for Bearer.

**Endpoint:** `POST /forgot-password/reset`  
Alias: `POST /reset-password`

**Request Payload:**
```json
{
  "mobile": "9876543210",
  "password": "NewPass@123",
  "password_confirmation": "NewPass@123"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Password reset successfully. Complete MPIN to continue.",
  "mpin_set": true,
  "next_step": "verify_mpin",
  "client": {
    "id": 12,
    "name": "Demo Client",
    "mobile": "9876543210"
  }
}
```

---

## 10. Forgot MPIN — Verify OTP + new MPIN → Bearer token

OTP + new MPIN in one step. No extra reset call needed.

**Endpoint:** `POST /forgot-mpin/verify-otp`

**Request Payload:**
```json
{
  "mobile": "9876543210",
  "otp": "123456",
  "mpin": "1234",
  "mpin_confirmation": "1234",
  "fcm_token": "YOUR_FCM_TOKEN",
  "device_id": "android-device-001"
}
```

**Response:** same Bearer shape as section 6/7, message `"MPIN reset successfully. Logged in."`

---

## 11. Profile (Me)

**Endpoint:** `GET /me`  
**Headers:** `Authorization: Bearer <token>`  
**Request Payload:** None

**Response:**
```json
{
  "status": true,
  "data": {
    "user": {
      "id": 45,
      "name": "Demo Client",
      "phone": "9876543210",
      "email": "demo@example.com",
      "username_hint": "demo@example.com"
    },
    "client": {
      "id": 12,
      "name": "Demo Client",
      "mobile": "9876543210",
      "email": "demo@example.com",
      "mpin_set": true,
      "status": "pending",
      "kyc_status": "pending",
      "kyc_verified": false,
      "is_active": false
    }
  }
}
```

If using ngrok, also send `ngrok-skip-browser-warning: 1`. URL must have **no space**.

---

## 12. Change MPIN (logged in)

**Endpoint:** `POST /change-mpin`  
**Headers:** `Authorization: Bearer <token>`

**Request Payload:**
```json
{
  "current_mpin": "1234",
  "mpin": "5678",
  "mpin_confirmation": "5678"
}
```

**Response:**
```json
{
  "status": true,
  "message": "MPIN changed successfully"
}
```

---

## 13. Logout

Expires FCM for this device and revokes the Bearer token.

**Endpoint:** `POST /logout`  
**Headers:** `Authorization: Bearer <token>`

**Request Payload:**
```json
{
  "device_id": "android-device-001"
}
```

`device_id` optional.

**Response:**
```json
{
  "status": true,
  "message": "Logged out successfully"
}
```

---

## 14. Refresh FCM (logged in)

**Endpoint:** `POST /api/users/user/fcm-token`  
**Headers:** `Authorization: Bearer <token>`

**Request Payload:**
```json
{
  "fcm_token": "YOUR_FCM_TOKEN",
  "device_id": "android-device-001",
  "device_name": "Pixel",
  "device_model": "Pixel 7"
}
```

Aliases: `/api/users/user/device`, `/api/users/fcm-token/update`, `/api/users/fcm-token`

---

## Endpoint checklist

| # | Method | URL | Auth / body |
|---|--------|-----|-------------|
| 1 | GET | `/api/customer/locations` | Public — use `id` as register `location_id` |
| 1b | GET | `/api/customer/auth/register-dropdowns` | Public — all Add Client select options |
| 2 | POST | `/api/customer/auth/register` | All Add Client fields + photos (multipart) |
| 3 | POST | `/api/customer/auth/create-password` | mobile, password, password_confirmation |
| 3 | POST | `/api/customer/auth/login` | username, password, fcm_token (stores FCM) |
| 4 | POST | `/api/customer/auth/send-otp` | mobile, purpose |
| 5 | POST | `/api/customer/auth/verify-otp` | mobile, otp, fcm_token (stores FCM) |
| 6 | POST | `/api/customer/auth/set-mpin` | mobile, mpin, mpin_confirmation → **Bearer** |
| 7 | POST | `/api/customer/auth/verify-mpin` | Bearer + `mpin` (no mobile) |
| 8 | POST | `/api/customer/auth/forgot-password/send-otp` | mobile |
| 9 | POST | `/api/customer/auth/forgot-password/verify-otp` | mobile, otp |
| 10 | POST | `/api/customer/auth/forgot-password/reset` | mobile, password, password_confirmation |
| 11 | POST | `/api/customer/auth/forgot-mpin/send-otp` | mobile |
| 12 | POST | `/api/customer/auth/forgot-mpin/verify-otp` | mobile, otp, mpin, mpin_confirmation → **Bearer** |
| 13 | GET | `/api/customer/auth/me` | Bearer |
| 14 | POST | `/api/customer/auth/change-mpin` | Bearer |
| 15 | POST | `/api/customer/auth/logout` | Bearer |
| 16 | POST | `/api/users/user/fcm-token` | Bearer |

---

## Banners (Loan / Chit / FD)

Public. Also returned inside `GET /api/customer/app-settings` as `banners.loan`, `banners.chit`, `banners.fd`.

Upload banners in **Admin → Mobile Apps → Customer App** (Overview, Loan, Chit, FD galleries).

**Endpoint:** `GET /api/customer/banners`  
**Optional:** `GET /api/customer/banners/loan` (`overview` | `loan` | `chit` | `fd`)

```json
{
  "success": true,
  "data": {
    "overview": ["http://localhost/financeandchitfund/public/uploads/mobile-apps/customer/banner_1.jpg"],
    "loan": ["http://localhost/financeandchitfund/public/uploads/mobile-apps/customer/loan_banner_1.jpg"],
    "chit": [],
    "fd": []
  }
}
```

---

## Loan types

Public.

**Endpoint:** `GET /api/customer/loan-types`  
**Detail:** `GET /api/customer/loan-types/{id}`

Each type includes `icon`, `image`, `banner`, and nested `products` (min/max amount, tenure, rate).

---

## Chit schemes

Public.

**Endpoint:** `GET /api/customer/chit-schemes`  
**Detail:** `GET /api/customer/chit-schemes/{id}` (includes open `groups`)

---

## FD schemes

Public.

**Endpoint:** `GET /api/customer/fd-schemes`  
**Detail:** `GET /api/customer/fd-schemes/{id}`

Includes `interest_frequency` (`monthly` / `quarterly` / `yearly`), min/max amount, tenure.

---

## Loan application (Bearer + KYC verified)

**Dropdowns:** `GET /api/customer/loans/dropdowns`

```json
{
  "success": true,
  "data": {
    "loan_types": [{ "id": 1, "name": "Personal Loan" }],
    "loan_products": [{
      "id": 2,
      "loan_name": "Personal EMI",
      "loan_code": "PL001",
      "loan_type_id": 1,
      "loan_amount_min": 10000,
      "loan_amount_max": 200000,
      "interest_rate": 18,
      "min_tenure": 6,
      "max_tenure": 24,
      "term_unit": "months"
    }],
    "loan_modes": [{ "value": "emi", "label": "EMI" }, { "value": "interest_only", "label": "Open Loan" }],
    "repayment_frequencies": [{ "value": "monthly", "label": "Monthly" }],
    "emi_days": [1, 2, 3]
  }
}
```

**Apply:** `POST /api/customer/loans/apply`

```json
{
  "loan_code": "PL001",
  "loan_amount": 50000,
  "loan_mode": "emi",
  "tenure": 12,
  "repayment_frequency": "monthly",
  "emi_day": 5,
  "emi_start_date": "2026-09-05"
}
```

`loan_product_id` can be sent instead of `loan_code`.

**List / detail:** `GET /api/customer/loans/applications`  
`GET /api/customer/loans/applications/{id}`

---

## Chit application (Bearer + KYC verified)

**Dropdowns:** `GET /api/customer/chits/dropdowns`

Use `available_groups[].id` as `group_id`. `collection_frequencies` and `chit_need_months` are dropdowns.

**Apply:** `POST /api/customer/chits/apply`

```json
{
  "group_id": 5,
  "collection_frequency": "monthly",
  "chit_need_month": 6
}
```

**List / detail:** `GET /api/customer/chits/applications`  
`GET /api/customer/chits/applications/{id}`

---

## FD application (Bearer + KYC verified)

**Dropdowns:** `GET /api/customer/fd/dropdowns`

Use `schemes[].id` as `scheme_id`. `payout_options` is the payout dropdown. Interest frequency comes from the selected scheme (`monthly` / `quarterly` / `yearly`).

**Apply:** `POST /api/customer/fd/apply`

```json
{
  "scheme_id": 1,
  "deposit_amount": 100000,
  "tenure": 12,
  "deposit_date": "2026-08-19",
  "start_date": "2026-08-19",
  "payout_option": "wallet",
  "nominee_name": "Jane Doe",
  "nominee_relation": "Spouse"
}
```

**List / detail:** `GET /api/customer/fd/applications`  
`GET /api/customer/fd/applications/{id}`

