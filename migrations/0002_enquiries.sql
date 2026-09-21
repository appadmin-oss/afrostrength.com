-- ─────────────────────────────────────────────────────────────────────────
-- Project enquiries from the homepage.
--
-- The homepage has one job — get a qualified enquiry — and until now it had
-- nowhere to put one. This is that place.
--
-- ── Why it is not a contractor job ───────────────────────────────────────
--
-- `jobs` already exists and holds a client looking for a tradesperson. This
-- is somebody asking the STUDIO to build them a brand or a piece of software.
-- Different work, different people answering it, different promise attached
-- (one working day, stated on the page), and a studio enquiry appearing in a
-- contractor's job board would leak the client's details to people who were
-- never meant to see them.
--
-- ── Consent is a column, not an assumption ───────────────────────────────
--
-- The form asks "You may keep my details to reply to this enquiry" and the
-- answer is stored. Under the NDPA the lawful basis for holding a name and an
-- email here is that consent, so it is written down with the time it was
-- given rather than inferred from the row existing.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS enquiries (
  id          VARCHAR(40)  NOT NULL PRIMARY KEY,
  ref         VARCHAR(20)  NOT NULL,
  -- What they said needs to change. The whole point of the form.
  problem     TEXT         NOT NULL,
  -- brand | digital | software | unsure. Free-ish text rather than an enum:
  -- the list is a question on a marketing page and will be reworded before
  -- it is ever worth a migration.
  kind        VARCHAR(40)  NULL,
  full_name   VARCHAR(200) NULL,
  email       VARCHAR(320) NOT NULL,
  consent     TINYINT(1)   NOT NULL DEFAULT 0,
  consent_at  DATETIME     NULL,
  -- Where they came from, if a link said. A label, not a tracker.
  source      VARCHAR(120) NULL,
  state       ENUM('new','answered','closed') NOT NULL DEFAULT 'new',
  created_at  DATETIME     NOT NULL,
  answered_at DATETIME     NULL,
  UNIQUE KEY uq_enquiry_ref (ref),
  KEY ix_enquiries_state (state, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- The outbox.
--
-- This app had no way to send anything. An enquiry that arrives and tells
-- nobody is the same failure the academy's enquiry form had, so the table
-- comes with the feature rather than after it.
--
-- Queued, never sent inline: a slow SMTP server must not make somebody wait
-- on a Send button, or press it twice. Rows sit here until something drains
-- them — see the note in lib/enquiries.php about what is still missing.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS notifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind        VARCHAR(60)  NOT NULL,
  to_email    VARCHAR(320) NOT NULL,
  subject     VARCHAR(300) NOT NULL,
  body        MEDIUMTEXT   NOT NULL,
  body_html   MEDIUMTEXT   NULL,
  status      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_error  VARCHAR(500) NULL,
  created_at  DATETIME     NOT NULL,
  sent_at     DATETIME     NULL,
  KEY ix_notifications_pending (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
