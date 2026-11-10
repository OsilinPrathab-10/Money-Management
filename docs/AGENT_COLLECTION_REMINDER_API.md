# Agent Collection Notes & Follow-up Reminder API

Comprehensive API documentation for setting and retrieving collection reminders, postponing dues, and viewing follow-up history for **Pending** and **Overdue** Loan EMIs and Chit Installments.

---

## Overview

When an agent visits a client for collection and the client is not available or requests to postpone the payment date:
1. The agent submits a **Follow-up Note with Reminder Date** via the mobile app / web interface.
2. The note and status (`postponed`, `promise_to_pay`, `not_available`, etc.) are recorded into the system history.
3. On that specified **reminder date**, the EMI or Chit installment automatically displays as active for collection on that day (`is_reminder_today = true`), prioritized at the top of the collection list.
4. Overdue reminders (`is_reminder_overdue = true`) are highlighted with alert indicators until collected or rescheduled.

---

## Base URL & Authentication

- **Base URL:** `https://abroad-wednesday-republican-simply.trycloudflare.com/api/agent` (or `{{base_url}}/api/agent`)
- **Headers:**
  ```http
  Authorization: Bearer <agent_bearer_token>
  Accept: application/json
  Content-Type: application/json
  ```

---

## 1. POST: Set Reminder / Add Follow-up Note

Records a follow-up note and reminder date for a pending or overdue Loan EMI or Chit Installment.

### Endpoint
`POST /api/agent/agent-collections/notes`

### Request Parameters (Supports both Web Admin and Mobile App keys)

| Parameter | Type | Required | Description |
|---|---|---|---|
| `type` | String | **Yes** | Collection type: `loan` or `chit`. |
| `emi_id` | Integer | Optional | Explicit Loan EMI ID (matches Web Admin). |
| `installment_id` | Integer | Optional | Explicit Chit Installment ID (matches Web Admin). |
| `id` | Integer / String | Optional | ID of the Loan EMI (`emi_id`) or Chit Installment (`installment_id`). Supports numeric or prefixes like `loan_7178` or `chit_448`. |
| `note` | String | **Yes** | Follow-up note or reason for postponement (matches Web Admin). |
| `remarks` | String | Optional | Alias for `note`. |
| `reminder_date` | String | Optional | Postponed reminder date format: `YYYY-MM-DD` (e.g. `2026-10-06`) or `YYYY-MM-DD HH:mm:ss`. If postponed to another day, the due is hidden today and sent only on that reminder day. |
| `status` | String | Optional | Follow-up status. Default: `postponed`. Allowed values: `postponed`, `promise_to_pay`, `not_available`, `visit_scheduled`, `dispute`, `general`. |
| `loan_account_id` | Integer | Optional | Loan Account ID (optional, auto-resolved from EMI). |
| `group_member_id` | Integer | Optional | Chit Group Member ID (optional, auto-resolved from Installment). |
| `client_id` | Integer | Optional | Client ID (optional, auto-resolved). |
| `latitude` | Numeric | Optional | GPS latitude of the agent at visit time. |
| `longitude` | Numeric | Optional | GPS longitude of the agent at visit time. |

---

### Example 1: Set Reminder for Loan EMI (Pending / Overdue)

#### Request:
```http
POST /api/agent/agent-collections/notes HTTP/1.1
Host: abroad-wednesday-republican-simply.trycloudflare.com
Authorization: Bearer {{agent_token}}
Content-Type: application/json
Accept: application/json

{
  "type": "loan",
  "emi_id": 7178,
  "note": "Client not available at shop today. Asked to collect tomorrow morning.",
  "reminder_date": "2026-10-06",
  "status": "postponed"
}
```

#### Response (200 OK):
```json
{
  "success": true,
  "message": "Follow-up reminder recorded successfully.",
  "data": {
    "note_id": 5,
    "type": "loan",
    "emi_id": 4863,
    "installment_id": null,
    "reminder_date": "2026-10-06",
    "reminder_date_formatted": "06-10-2026",
    "reminder_time": null,
    "status": "postponed",
    "remarks": "Client not available at shop today. Asked to collect tomorrow morning.",
    "created_at": "05-10-2026 10:44 AM"
  }
}
```

---

### Example 2: Set Reminder for Chit Installment (Pending / Overdue)

#### Request:
```http
POST /api/agent/agent-collections/notes HTTP/1.1
Host: abroad-wednesday-republican-simply.trycloudflare.com
Authorization: Bearer {{agent_token}}
Content-Type: application/json
Accept: application/json

{
  "type": "chit",
  "installment_id": 448,
  "note": "Client promised to pay online or cash on Wednesday.",
  "reminder_date": "2026-10-07",
  "status": "promise_to_pay"
}
```

#### Response (200 OK):
```json
{
  "success": true,
  "message": "Follow-up reminder recorded successfully.",
  "data": {
    "note_id": 6,
    "type": "chit",
    "emi_id": null,
    "installment_id": 448,
    "reminder_date": "2026-10-07",
    "reminder_date_formatted": "07-10-2026",
    "reminder_time": null,
    "status": "promise_to_pay",
    "remarks": "Client promised to pay online or cash on Wednesday.",
    "created_at": "05-10-2026 10:45 AM"
  }
}
```

---

## 2. GET: Today's Collection & Scheduled Reminders

Retrieves **Today's Collections & Reminders** for the logged-in agent.
- **Today's Dues:** All EMIs / Chit installments with `due_date = today` (unless postponed to another date).
- **Scheduled Reminders for Today:** Any EMI / Chit installment with a reminder scheduled for today (`reminder_date = today`), prioritized at the very top with `is_reminder_today: true`.
- **Postponed Dues:** If an EMI or chit is postponed to a future date (e.g. tomorrow or next week), it is automatically **hidden today** and will **only be sent in this API on that reminder day**.

### Endpoint
```http
GET /api/agent/agent-collections/pending-follow-ups
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `type` | String | Optional | `all` | Filter by type: `loan`, `chit`, or `all`. |
| `page` | Integer | Optional | `1` | Pagination page number. |
| `per_page` | Integer | Optional | `50` | Number of items per page. |
| `date` | String | Optional | Today | Specific collection date (`YYYY-MM-DD`). Defaults to current date (today). |
| `only_reminders` / `has_reminder` | Boolean (0/1) | Optional | `0` | If `1` or `true`, returns strictly items that have an active reminder set for that day. |
| `status` | String | Optional | `null` | Filter by follow-up status (`postponed`, `promise_to_pay`, etc.). |
| `search` | String | Optional | `null` | Search query for client name, phone number, loan account number, or chit group code. |

---

### Example Request (As requested)

```http
GET /api/agent/agent-collections/pending-follow-ups?type=loan&page=1&per_page=50 HTTP/1.1
Host: abroad-wednesday-republican-simply.trycloudflare.com
Authorization: Bearer {{agent_token}}
Accept: application/json
```

---

### Example Response (200 OK)

```json
{
  "success": true,
  "message": "Pending follow-ups retrieved successfully",
  "data": [
    {
      "id": "loan_4863",
      "type": "loan",
      "emi_id": 4863,
      "installment_id": null,
      "loan_account_id": 239,
      "account_or_group": "MFWC-00001",
      "label": "EMI #5",
      "instalment_number": 5,
      "due_date": "2026-09-20",
      "due_date_formatted": "20-09-2026",
      "days_overdue": 15,
      "amount_due": 1000.00,
      "total_amount": 1000.00,
      "status": "overdue",
      "client_id": 327,
      "client_name": "S Ramesh",
      "client_phone": "9876543210",
      "client_image": "https://abroad-wednesday-republican-simply.trycloudflare.com/storage/clients/327.jpg",
      "product_name": "Daily Micro Loan",
      "reminder_date": "2026-10-05",
      "reminder_date_formatted": "05-10-2026",
      "reminder_time": null,
      "is_reminder_today": true,
      "is_reminder_overdue": false,
      "followup_status": "postponed",
      "latest_note": "Client postponed payment. Reminder for today.",
      "created_by": "Agent John",
      "created_at": "05-10-2026 10:44 AM",
      "notes_history": [
        {
          "id": 5,
          "source": "note",
          "note": "Client postponed payment. Reminder for today.",
          "reminder_date": "2026-10-05",
          "reminder_date_formatted": "05-10-2026",
          "status": "postponed",
          "created_by": "Agent John",
          "created_at": "05-10-2026 10:44 AM"
        },
        {
          "id": 1,
          "source": "note",
          "note": "Client requested callback tomorrow morning. Postponed collection note.",
          "reminder_date": "2026-10-04",
          "reminder_date_formatted": "04-10-2026",
          "status": "postponed",
          "created_by": "Super Admin",
          "created_at": "04-10-2026 04:30 PM"
        }
      ]
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "stats": {
    "total_follow_ups": 1,
    "today_reminders": 1,
    "overdue_reminders": 0,
    "loan_count": 1,
    "chit_count": 0,
    "total_amount_due": 1000.00
  }
}
```

---

### Example with `type=all` (Combined Loans & Chits)

```http
GET /api/agent/agent-collections/pending-follow-ups?type=all&page=1&per_page=50 HTTP/1.1
Host: abroad-wednesday-republican-simply.trycloudflare.com
Authorization: Bearer {{agent_token}}
Accept: application/json
```

#### Response Stats:
```json
{
  "stats": {
    "total_follow_ups": 2,
    "today_reminders": 2,
    "overdue_reminders": 0,
    "loan_count": 1,
    "chit_count": 1,
    "total_amount_due": 6000.00
  }
}
```

---

## 3. GET: Dedicated Agent Reminders API

Specifically retrieves **Collection Reminders** for the logged-in agent, categorized by **Today**, **Overdue**, or **Upcoming**.

### Endpoint
```http
GET /api/agent/agent-collections/reminders
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|---|---|---|---|---|
| `filter` | String | Optional | `all` | Filter reminders: `today`, `overdue`, `upcoming`, or `all`. |
| `type` | String | Optional | `all` | Filter by type: `loan`, `chit`, or `all`. |
| `date` | String | Optional | `null` | Filter by specific reminder date (`YYYY-MM-DD`). |
| `status` | String | Optional | `null` | Filter by follow-up status (`postponed`, `promise_to_pay`, etc.). |
| `search` | String | Optional | `null` | Search client name, phone number, loan account, chit group. |
| `page` | Integer | Optional | `1` | Pagination page number. |
| `per_page` | Integer | Optional | `50` | Number of items per page. |

### Example Request
```http
GET /api/agent/agent-collections/reminders?type=loan&filter=today&page=1&per_page=50 HTTP/1.1
Host: abroad-wednesday-republican-simply.trycloudflare.com
Authorization: Bearer {{agent_token}}
Accept: application/json
```

### Example Response (200 OK)
```json
{
  "success": true,
  "message": "Reminders retrieved successfully",
  "data": [
    {
      "id": "loan_7178",
      "type": "loan",
      "emi_id": 7178,
      "loan_account_id": 295,
      "account_number": "SDSWC50K0295",
      "loan_name": "Personal Loan",
      "client_id": 358,
      "customer_id": "SDS-C0358",
      "client_name": "Giri Rathinam",
      "client_phone": "9566585853",
      "label": "EMI #27",
      "instalment_number": 27,
      "amount_due": 2000,
      "due_date": "2026-10-05",
      "status": "pending",
      "reminder_date": "2026-10-05",
      "reminder_date_formatted": "05-10-2026",
      "reminder_time": null,
      "followup_status": "postponed",
      "is_reminder_today": true,
      "is_reminder_overdue": false,
      "is_reminder_upcoming": false,
      "latest_note": "Customer promised to pay at shop today",
      "created_by": "Alka (Agent)",
      "created_at": "05-10-2026 12:45 PM",
      "notes_history": []
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "stats": {
    "total_reminders": 4,
    "today_reminders": 1,
    "overdue_reminders": 0,
    "upcoming_reminders": 3,
    "total_amount_due": 17221.00
  }
}
```

---

## 4. GET: Follow-up Notes History

Fetches the chronological history of all notes and follow-ups for a specific EMI or Chit installment.

### Endpoint
```http
GET /api/agent/agent-collections/notes/{type}/{id}
```

### Path Parameters
- `type`: `loan` or `chit`
- `id`: `emi_id` (for loan) or `installment_id` (for chit)

### Example Request
```http
GET /api/agent/agent-collections/notes/loan/4863 HTTP/1.1
Host: abroad-wednesday-republican-simply.trycloudflare.com
Authorization: Bearer {{agent_token}}
Accept: application/json
```

### Example Response (200 OK)
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "note": "Client postponed payment. Reminder for today.",
      "reminder_date": "2026-10-05",
      "reminder_date_formatted": "05-10-2026",
      "reminder_time": null,
      "status": "postponed",
      "created_by": "Agent John",
      "created_at": "05-10-2026 10:44 AM"
    },
    {
      "id": 1,
      "note": "Client requested callback tomorrow morning. Postponed collection note.",
      "reminder_date": "2026-10-04",
      "reminder_date_formatted": "04-10-2026",
      "reminder_time": null,
      "status": "postponed",
      "created_by": "Super Admin",
      "created_at": "04-10-2026 04:30 PM"
    }
  ]
}
```

---

## Key Fields Explained

| Key | Description |
|---|---|
| `is_reminder_today` | `true` if `reminder_date` equals today's date. Display with prominent warning badge. |
| `is_reminder_overdue` | `true` if `reminder_date` was prior to today and not completed. Needs urgent attention. |
| `reminder_date` | Date (`YYYY-MM-DD`) on which this EMI or Chit installment is scheduled to be collected. |
| `reminder_date_formatted`| Preformatted date (`DD-MM-YYYY`) for UI rendering. |
| `followup_status` | Status code: `postponed`, `promise_to_pay`, `not_available`, `visit_scheduled`, `dispute`, `general`. |
| `latest_note` | Most recent note or remark text entered by agent/admin. |
| `notes_history` | Complete history array of previous follow-ups for this specific installment/EMI. |
| `days_overdue` | Number of days passed since the original installment due date. |

---

## Status Reference

| Status Code | Label | Color Badge | Meaning |
|---|---|---|---|
| `postponed` | Postponed | Warning (Yellow/Orange) | Client requested to collect on a later date. |
| `promise_to_pay` | Promise to Pay (PTP) | Primary (Blue) | Client committed to pay by the reminder date. |
| `not_available` | Client Not Available | Danger (Red) | Client was not present at home or shop during visit. |
| `visit_scheduled` | Visit Scheduled | Info (Cyan) | Physical visit planned for collection. |
| `dispute` | Payment Dispute | Danger (Red) | Client disputes amount or terms. |
| `general` | General Note | Secondary (Gray) | General follow-up observation. |

---

## Error Handling

### 422 Unprocessable Entity (Validation Error)
```json
{
  "success": false,
  "message": "The reminder date field is required.",
  "errors": {
    "reminder_date": ["The reminder date field is required."]
  }
}
```

### 403 Forbidden (Unauthorized Agent)
```json
{
  "success": false,
  "message": "Unauthorized to access this loan EMI"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "EMI record not found"
}
```
