# CIBIL Report

Implementation note for the admin **CIBIL Report** menu. It pulls a multi-bureau credit report from API Hub (`POST v3/cibil`), stores the JSON, downloads the TransUnion PDF before the signed link expires, and can attach the report to a client.

Roles that see the menu: **Admin**, **CreditVerifier**.

## Menu

Sidebar item under Business Modules.

| Item | URL | Route name |
| --- | --- | --- |
| CIBIL Report | `/cibil-report` | `cibil-report.index` |
| Report detail | `/cibil-report/{id}` | `cibil-report.show` |

Icon: `ri-bar-chart-box-line`.

The same report is also opened from a client's **CIBIL** tab (`/clients/view/cibil/{clientId}`, route `client-view-cibil`).

Each successful request is counted as one `cibil` API hit (API Hits & Usage). PAN and mobile stored on the hit are masked.

## Screens in the menu

### 1. New Report (`?tab` omitted or anything other than `history`)

Form `POST /cibil-report`.

| Field | Rule |
| --- | --- |
| `client_id` | Optional. Existing client. Choosing one fills name, mobile, PAN, and gender. |
| `pan` | Required. `ABCDE1234F`. Stored uppercase, spaces removed. |
| `name` | Required. Full name as on PAN. Max 255. |
| `mobile` | Required. 10 digits, starts with 6–9. Non-digits stripped. |
| `gender` | Required. `male`, `female`, or `other`. |
| `consent` | Required checkbox. The customer has agreed to the bureau pull. |

On success the user is sent to the report page and the saved TransUnion PDF starts downloading. On failure the user is still sent to the report page, which shows `error_message`.

The page also shows whether test mode is on and the bureau path (`v3/cibil`).

Header counts (all reports, not only the current filter):

- Total reports
- Reports this month
- Linked to a client
- TransUnion PDFs saved
- Average score (rows that have a score)

### 2. Report History (`?tab=history`)

Paginated, 15 per page. Columns:

| Column | What it shows |
| --- | --- |
| Applicant | Name, PAN, mobile. Badges: Test, Failed, or link countdown. |
| Score | Score out of 900 and rating colour. Empty when there is no score. |
| Client | Linked client, or “Not linked” with a link to attach or create a client. |
| TransUnion PDF | Saved, test mode, not saved yet, or link expired. |
| Requested | Date and the staff user who fetched it. |
| Actions | View, download PDF, delete. |

Filters (GET, kept on later pages):

| Query | Values |
| --- | --- |
| `search` | Name, PAN, or mobile |
| `status` | `success`, `failed`, or blank for all |
| `linked` | `linked`, `unlinked`, or blank |
| `date_preset` | `today`, `this_week`, `this_month`, `custom` |
| `from`, `to` | Used when the preset is `custom` |

Status pills on the history header: All, Success, Failed.

### 3. Report detail

Shown after a fetch, and from History → View.

Top actions:

- Download TransUnion PDF
- View PDF in the browser (`?inline=1`)
- Retry PDF download while the signed link is still valid
- Delete the report and its saved file

Three cards:

1. **CIBIL score** — number out of 900, rating badge, score name and score date.
2. **TransUnion PDF link** — countdown until `link_expires_at`. Live mode can open the bureau link and shows whether the PDF was saved. Test mode does not download the link.
3. **Client** — linked client, or suggested matches (same PAN or mobile), a client picker, and **Create new client from this report**.

Summary cards (from `data.analysis.summary` and inquiries):

- Total accounts (active and closed)
- Current balance and sanctioned total
- Overdue amount and how many accounts are overdue
- On-time payment rate and credit utilisation
- Enquiry count and enquiries in the last 90 days

Then:

- **Observations** — each `data.analysis.observations[]` item (`type`, `text`)
- **Credit Accounts** — filter All / Active / Overdue
- **Enquiries**
- **Personal information as reported by the bureau**

Credit account columns:

| Column | Source |
| --- | --- |
| Lender / type | `lender_name`, `account_type`, ownership from `bureau_details.cibil` |
| Account | Last 4 of `account_number` |
| Opened | `date_opened`, and `date_closed` when present |
| Status | Active when `is_open` is true, otherwise Closed |
| Sanctioned | `sanctioned_amount`, else `credit_limit` |
| Balance | `current_balance` |
| Overdue | `amount_past_due` (row highlighted when greater than 0) |
| EMI | `emi_amount` |
| Payment history (DPD) | `dpd_analysis.on_time_pct`, `max_dpd`, and the last 12 months |

Enquiry columns: date, lender, amount.

Personal information: names and dates of birth, ID type and number, unique last-10 phone digits, unique emails, addresses with pin and date reported.

### 4. Client CIBIL tab

`GET /clients/view/cibil/{clientId}`.

Shows the client name, customer id, phone, PAN, the latest successful score, and a table of that client's reports (score, date, staff, PDF, view). **New CIBIL report** opens the menu with `client_id` preselected.

Creating a client from a report prefills name, phone, PAN, gender, date of birth, email, and address. After the client is saved, unlinked reports with the same PAN are linked, and `clients.cibil_score` is set to the latest scored report.

## Score bands

| Score | Rating | Colour |
| --- | --- | --- |
| 750–900 | Excellent | green |
| 700–749 | Good | blue |
| 650–699 | Fair | orange |
| below 650 | Below Average | red |
| missing | No score | grey |

The ring on the history list treats 300 as the bottom and 900 as the top: `(score - 300) / 6` percent.

## Routes

All of these sit behind the admin web auth used by the rest of the panel.

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/cibil-report` | `cibil-report.index` | New Report or History |
| POST | `/cibil-report` | `cibil-report.store` | Fetch a report |
| GET | `/cibil-report/{id}` | `cibil-report.show` | Detail. Also retries the PDF download if the link is still valid and the file is missing. |
| GET | `/cibil-report/{id}/pdf` | `cibil-report.pdf` | Download the saved TransUnion PDF. `?inline=1` opens it. |
| POST | `/cibil-report/{id}/pdf` | `cibil-report.retry-pdf` | Download the bureau link again. |
| POST | `/cibil-report/{id}/link` | `cibil-report.link` | Body: `client_id`. Refuses a second, different client. |
| GET | `/cibil-report/{id}/create-client` | `cibil-report.create-client` | Opens Add Client prefilled from the report. |
| DELETE | `/cibil-report/{id}` | `cibil-report.destroy` | Deletes the row and the PDF file. Returns to History. |
| GET | `/clients/view/cibil/{clientId}` | `client-view-cibil` | Client tab. |

## Bureau request

```http
POST {APIHUB_BASE_URL}/v3/cibil
X-Client-Api-ID: {APIHUB_CLIENT_API_ID}
X-Client-Api-Secret: {APIHUB_CLIENT_API_SECRET}
Accept: application/json
Content-Type: application/json
```

Force `https` even if the base URL is `http`. A redirect from `http` to `https` would turn this POST into a GET. Timeout: 90 seconds.

```json
{
  "pan": "ABCDE1234F",
  "name": "APPLICANT NAME",
  "mobile": "9876543210",
  "gender": "male"
}
```

`gender` is lowercase `male`, `female`, or `other`.

Config:

| Key | Env | Default |
| --- | --- | --- |
| Base URL | `APIHUB_BASE_URL` | `http://apihub.services` |
| Client id | `APIHUB_CLIENT_API_ID` | falls back to `APIHUB_API_KEY`, then `API_KEY` |
| Client secret | `APIHUB_CLIENT_API_SECRET` | falls back to `APIHUB_API_SECRET`, then `SECRET_KEY` |
| Path | `APIHUB_CIBIL_PATH` | `v3/cibil` |
| Test mode | `APIHUB_CIBIL_MOCK` | `false` |
| Sample file | `APIHUB_CIBIL_MOCK_FILE` | `resources/cibil/sample-response.json` |
| Link lifetime when the URL has no expiry | `APIHUB_CIBIL_LINK_TTL` | `600` seconds |

Test mode (`APIHUB_CIBIL_MOCK=true`) returns the sample file and never calls the bureau. The request is still stored and counted, with `is_mock = true`. Test mode does not download the TransUnion PDF.

## Bureau response

A successful call looks like this. Values below are the shape, not a live report.

```json
{
  "success": true,
  "status_code": 200,
  "message": "Multi bureau fetch completed",
  "data": {
    "pan": "ABCDE1234F",
    "doc_id": "mbureau_…",
    "pdf_url": "https://…/consolidated.pdf",
    "results": {
      "cibil": {
        "success": true,
        "message": "cibil report fetched successfully",
        "credit_report_link": "https://…signed-transunion.pdf?X-Amz-Date=…&X-Amz-Expires=600&X-Amz-Signature=…",
        "data": {
          "client_id": "credit_report_cibil_pdf_…",
          "name": "APPLICANT NAME",
          "mobile": "9876543210",
          "pan": "ABCDE1234F",
          "gender": "male",
          "user_email": null,
          "credit_score": "648",
          "credit_report_link": "https://…same signed pdf…",
          "credit_report_base64": null,
          "credit_report": [ { } ]
        }
      }
    },
    "analysis": { }
  }
}
```

Treat the call as a success only when all of these are true:

- no transport error
- `success === true`
- `data.results.cibil.success === true`

Score used by the screen, first one that is numeric and greater than 0:

1. `data.results.cibil.data.credit_score`
2. `data.analysis.scores.cibil.value`

PDF link, first one present:

1. `data.results.cibil.credit_report_link`
2. `data.results.cibil.data.credit_report_link`

The signed link usually lives about 10 minutes (`X-Amz-Date` plus `X-Amz-Expires`). Download it with the URL exactly as returned. Do not rebuild the query string. Accept the body only when the HTTP status is 2xx and the file starts with `%PDF`. Store that file. Do not generate a replacement PDF.

`data.pdf_url` is the consolidated multi-bureau PDF. The menu saves and shows the TransUnion `credit_report_link`, not this consolidated file.

### `credit_report[0]` (raw CIBIL)

| Key | Contents |
| --- | --- |
| `control_number` | Bureau control number |
| `names[]` | `name`, `birthDate`, `gender`, `index` |
| `ids[]` | `idType` (`TaxId`, `SocialId`, `VoterId`, `DriversLicenseId`, `CkycId`, …), `idNumber`, `index` |
| `telephones[]` | `telephoneNumber`, `telephoneType`, `enquiryEnriched`, `index` |
| `emails[]` | `emailID`, `index` |
| `employment[]` | `accountType`, `dateReported`, `occupationCode`, `name`, `income` |
| `scores[]` | `score`, `scoreName`, `scoreCardName`, `scoreDate`, `reasonCodes[]` (`reasonCodeName`, `reasonCodeValue`) |
| `addresses[]` | `line1`, `line2`, `pinCode`, `addressCategory`, `dateReported`, `index` |
| `accounts[]` | Raw tradelines (see below) |
| `enquiries[]` | Raw bureau enquiries |
| `response` | Extra bureau payload |

Raw account fields include `accountType`, `accountNumber`, `memberShortName`, `ownershipIndicator`, `dateOpened`, `dateClosed`, `dateReported`, `lastPaymentDate`, `currentBalance`, `amountOverdue`, `highCreditAmount`, `emiAmount`, `repaymentTenure`, `termMonths`, `paymentHistory`, `paymentStartDate`, `paymentEndDate`, `paymentFrequency`, `monthlyPayStatus`, `creditFacilityStatus`, `suitFiledStatus`, `suitFiledWillfulDefaultWrittenOff`, `woAmountPrincipal`, `woAmountTotal`, `settlementAmount`, `interest_rate`, `collateral_type`, `collateral_value`, `cash_limit`, `actualPaymentAmount`.

### `data.analysis` (what the screen renders)

| Key | Contents |
| --- | --- |
| `scores.experian`, `scores.equifax`, `scores.cibil`, `scores.crif` | `value`, `rating`, `available` |
| `scores.average`, `average_rating`, `min`, `max`, `spread`, `count` | Combined score stats |
| `summary` | `total_accounts`, `active_accounts`, `closed_accounts`, `total_current_balance`, `total_past_due`, `total_sanctioned`, `accounts_with_overdue`, `secured_count`, `unsecured_count`, `revolving_count`, `credit_utilization`, `on_time_payment_rate`, `oldest_account_date`, `newest_account_date`, `bureau_coverage` |
| `account_summary[]` | One row per account for the accounts table |
| `consolidated_accounts[]` | Same accounts with `per_bureau` and `payment_history` |
| `personal_info` | `name`, `pan`, `mobile`, `email`, `dob`, `gender`, `address` |
| `inquiries[]` | `date`, `lender`, `purpose`, `amount`, `source` |
| `inquiry_summary` | `last_7_days`, `last_30_days`, `last_90_days`, `last_180_days` |
| `observations[]` | `type`, `text` |
| `bureau_availability` | `experian`, `equifax`, `cibil`, `crif` booleans |
| `crif_derived`, `crif_identity_variations` | Present when CRIF data exists |

Each `account_summary` item:

`account_number`, `lender_name`, `account_type`, `is_open`, `date_opened`, `date_closed`, `current_balance`, `sanctioned_amount`, `amount_past_due`, `credit_limit`, `emi_amount`, `tenure_months`, `interest_rate`, `portfolio_type`, `bureaus_present`, `bureau_count`, `best_bureau`, `bureau_details.cibil`, `dpd_analysis`.

`dpd_analysis`: `total_months`, `on_time_months`, `delayed_months`, `on_time_pct`, `max_dpd`, `current_dpd`, `dpd_buckets`, `last_12_months` (`month`, `dpd`), `worst_month`.

## What to store

Table `credit_score_histories` (model `CreditScoreHistory`). Keep the full bureau JSON in `report_json`.

| Column | Set from |
| --- | --- |
| `client_id`, `linked_at`, `linked_by` | Set when a client was chosen or later linked |
| `applicant_name`, `pan_number`, `phone`, `gender` | The request |
| `email` | `data.results.cibil.data.user_email` |
| `score` | Score rules above |
| `rating` | `data.analysis.scores.cibil.rating`, or the band table |
| `report_json` | Whole response |
| `status` | `success` or `failed` |
| `provider` | `apihub` |
| `is_mock` | Test mode |
| `doc_id` | `data.doc_id` |
| `credit_report_link` | Signed TransUnion URL |
| `link_expires_at` | From `X-Amz-Date` + `X-Amz-Expires`, otherwise now plus the configured TTL |
| `consolidated_pdf_url` | `data.pdf_url` |
| `pdf_path` | `cibil-reports/client_{id}/cibil_{historyId}_{Ymd_His}.pdf`, or `cibil-reports/unlinked/…` |
| `pdf_source` | `bureau` when the TransUnion file was saved |
| `pdf_status` | `pending`, `saved`, or `failed` |
| `pdf_error` | Why the PDF was not saved |
| `pdf_saved_at` | When the file was written |
| `error_message` | Bureau or transport message when status is `failed` |
| `created_by` | Staff user |

Also update `clients.cibil_score` to the latest non-null score for that client.

PDF files live on the local private disk, not in public storage.

## Failure response

If the HTTP call fails, times out, or `data.results.cibil.success` is not true:

- `status = failed`
- `error_message` is the transport error, else `data.results.cibil.message`, else the top-level `message`
- no PDF is downloaded
- the detail page shows the error and does not offer client linking

## Order of work for another project

1. Add the menu item and the nine routes above.
2. Add the history table and store the full JSON plus the columns in the table.
3. Post `pan`, `name`, `mobile`, and `gender` to `v3/cibil` with the two API Hub headers.
4. Mark success only when the top level and `results.cibil` both succeed.
5. Save the score, rating, `doc_id`, signed link, expiry, and `pdf_url`.
6. Download the signed link immediately and keep the PDF on a private disk.
7. Build New Report, History (filters above), and the detail page from `data.analysis` plus `credit_report[0]`.
8. Link a report to an existing client, or create a client from the names, phones, emails, and addresses in the bureau block.
9. Show the client's reports on the client CIBIL tab and copy the latest score onto the client.
10. Count every attempt, including test mode, as one CIBIL API hit. Mask PAN and mobile on that hit.
