# Mastermind HRMS — outstanding work

Started 2026-10-05. Items here are agreed but not built. Anything already
shipped lives in the git history, not in this file.

---

## 1. In-app calls (audio / video)

**Goal:** staff call each other from the app, the way WhatsApp does.

### Decision taken

**Version 1 is peer-to-peer, 1:1, with NO recording.**

That choice is what keeps it nearly free. The moment calls are recorded, the
media has to flow through a server, and the whole economic shape changes — see
§1.5.

### Where we are starting from

Nothing real-time exists yet:

- `BROADCAST_CONNECTION=log` — no broadcasting configured
- Staff chat updates by **polling every 30 seconds** (`partials/chat-panel.blade.php`)
- The production host **cannot** support calls: no persistent connections, no
  daemons (no crontab, `exec` blocked), no control over the server — the WAF
  episode in §2 proved the last point

So the infrastructure is the job. The application code is the smaller half.

### 1.1 Infrastructure needed

| piece | what it does | choice |
|---|---|---|
| **VPS** | hosts the two services below | ~$10–12/mo, 2 vCPU / 4 GB |
| **Signalling** | ring / answer / hang-up, and the WebRTC handshake | Laravel **Reverb** on the VPS |
| **STUN** | lets two phones discover how to reach each other | free (public STUN) |
| **TURN** | relays media when they cannot connect directly | **coturn** on the same VPS |

TURN is not optional. On Ugandan mobile networks — carrier-grade NAT on MTN and
Airtel — a high share of calls cannot connect directly and must relay. Budget as
if most do.

### 1.2 What one VPS actually covers

The cost is **capacity, not per call**. Nothing meters, and all 1,281 staff are
covered by the same box.

- Signalling: one WebSocket per *online* user. 1,281 is nothing on 2 GB.
- TURN, concurrent **relayed** calls: several hundred audio, or ~30–50 video.
- Transfer: relayed video ≈ 18 MB/min, audio ≈ 0.6 MB/min. 20,000 relayed video
  minutes/month ≈ 360 GB against a typical 2–20 TB allowance.

Realistic peak for this organisation is 10–30 concurrent calls. One VPS is
comfortable. Scale by adding a second TURN server — horizontal, no
re-architecture.

### 1.3 Application work

**Backend**
- Install and run Reverb; move `BROADCAST_CONNECTION` off `log`
- Signalling channels: offer / answer / ICE candidates / hang-up, authorised so
  only the two parties on a call can join its channel
- Call records: caller, callee, started, answered, ended, outcome (missed,
  declined, completed) — needed for history and for any later billing or audit
- Push on incoming call (see below)

**Flutter app**
- `flutter_webrtc`
- Microphone and camera permissions (and the Play Console data-safety answers
  that come with them)
- Incoming-call screen, in-call controls, call history
- **Android:** FCM high-priority message + a foreground service to ring a closed app
- **iOS:** PushKit + CallKit — Apple *requires* CallKit for VoIP, and it is its
  own App Store review surface

**Side benefit:** once Reverb exists, staff chat stops polling and becomes
real-time. Worth having on its own.

### 1.4 The limit being accepted

Peer-to-peer works for **1:1**. Three is borderline; four or more falls over —
in a mesh every handset uploads its video to every other participant, so the
phones break before the server does. Group calls need an SFU (§1.5).

### 1.5 If recording or group calls are wanted later

Recording **forces** an SFU even for 1:1, because a peer-to-peer call never
touches our servers and so cannot be recorded. That moves us from a flat VPS
bill to per-minute pricing.

Figures checked 2026-10-05 — **verify before committing, pricing moves**:

| | Daily.co | LiveKit Cloud |
|---|---|---|
| Audio participant-minute | $0.00099 | $0.0005 (Ship) |
| Video participant-minute | $0.004 | $0.0005 |
| Recording | $0.01349 per *recorded* minute | track egress $0.001/min |
| Bandwidth | included | **$0.12/GB downstream, billed separately** |
| Free allowance | 10,000 participant-min/month | 1,000–50,000 recording min by tier |

Recording is billed per **wall-clock minute, not per participant** — a 45-minute
call costs the same to record with 2 people or 10. So recording cost tracks
meeting hours, not headcount.

Worked estimates:
- 20 meetings/mo × 5 people × 45 min → **≈ $15/month** (the free allowance
  absorbs the call side entirely)
- 100 meetings/mo × 6 people × 60 min → **≈ $200/month** video, **≈ $125**
  audio-only

Two traps:
- LiveKit looks 8× cheaper per participant-minute but bills bandwidth separately
  at $0.12/GB. Compare on a full worked example, not the headline rate.
- Export recordings to our own object storage (Backblaze B2 / Wasabi) and keep
  only the URL. Provider storage is billed per retained minute and only grows.
  This also sidesteps §2 entirely, since nothing multipart touches our web server.

**Settle before building recording:** these are HR recordings — appraisals,
disciplinaries, grievances. Participant notification and consent, a retention
period, and who may replay one. That drives the schema and the permissions, so
it is a decision to take first, not after.

Storage sizes: audio ≈ 30–60 MB/hour, 720p video ≈ 500 MB–1 GB/hour. Audio-only
is an order of magnitude cheaper to keep, and for HR purposes is usually what
actually matters.

### 1.6 Suggested first step

VPS + Reverb + coturn, **audio-only 1:1, no recording**. Smallest thing that is
genuinely useful, forces the signalling layer into existence, makes chat
real-time as a side effect, and tells us what real usage looks like before
committing to video or an SFU.

---

## 2. BLOCKING — the server rejects every file upload

**This is live and affecting users now.** Not an application bug.

`mastermindconsults.co.ug` answers any multipart request carrying a file with a
**LiteSpeed 403, before PHP runs**:

| request to the same URL | result |
|---|---|
| plain POST | 419 — Laravel's CSRF page, so it reached the app |
| multipart, text field only | 419 — reached the app |
| multipart **containing a file** | **403, LiteSpeed's own error page** |

A 6-byte text file is blocked, so it is not size. The same 2 MB sent as base64
form data reaches the application fine — the rule targets multipart file parts
specifically.

**Breaks:** Company Documents, profile photos, meeting attachments, quality goal
evidence, and the public careers form — **applicants cannot submit a CV**.

**Tried and failed:** four `mod_security` / LiteSpeed directive variants in
`public/.htaccess`. All left the site healthy but the block in place — the rule
is enforced above the account. `.htaccess` was restored and verified unchanged.

**Fix:** HostGivers must disable or whitelist the upload rule. A ticket with the
reproduction has been prepared.

**Rejected workaround:** sending files base64-encoded as ordinary form data does
get through, but it bypasses the host's upload scanning. Decided against.

---

## 3. Smaller open items

- **HQ geofence is approximate.** Head office is set to the Kisaasi suburb
  centroid (0.36944, 32.58500, r=1200m) because Plot 28A Katula Road is not
  geocoded anywhere. Replace with a real reading —
  `Desktop\set_hq_coordinates.py` (dry run, then `--apply`) once any HQ employee
  clocks in at the office. None of the 30 ever has.
- **Africot Trading Ltd coordinates** are still the old shared point
  (0.3695844, 32.5981736, r=100m, site id 1 — seed data). Their 8 posted staff
  are fenced on it. Entering the real location was attempted on 2026-10-03 but
  did not save.
- **Applicant login says the wrong thing.** A staff account typed into the job
  applicant sign-in gets "That email and password do not match", because it is
  checked against `job_seekers` (currently 0 rows) rather than `users`. Should
  detect a staff email and point at "Mastermind staff login" instead.
- **Zero applicant accounts exist.** Worth checking whether the careers flow is
  broken earlier than the CV upload step (§2).
- **Payroll:** `charge_nssf` is now honoured (2026-10-05) — it previously required an NSSF_EMP component attached on Salary Setup, and 0 of Bidco`s 67 had one. The remaining statutory switches are still read by nobody (`charge_lst` is
  set on 1,246 of 1,247 employees and Local Service Tax is never deducted);
  `processRun()` filters employees on `now()` rather than the run period; WHT is
  stored only in `component_details` so reports summing `tax_amount` miss it; no
  guard on the silent zero-pay path when attendance is missing (run 20 is 439
  payslips of zero net).
- **Quality module:** unread badges on alerts, department/role audit targeting,
  richer report charts, a standalone preventive-action register.
