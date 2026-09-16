-- ─────────────────────────────────────────────────────────────────────────
-- Afrostrength contractors — the directory and the job board.
--
-- A separate product from Afrotech Academy, in a separate database, and that
-- separation is deliberate rather than tidy-minded.
--
-- A learner and a contractor are different data subjects held on different
-- lawful bases: a learner's record exists to teach them, a contractor's to
-- put them in front of paying clients. Under the NDPA, merging the two
-- databases means one consent has to cover both purposes, which it does not.
-- It also means a bug in a job board could read a learner's marks.
--
-- So: its own schema, its own accounts, its own deployment.
-- ─────────────────────────────────────────────────────────────────────────

-- Somebody who does the work.
CREATE TABLE IF NOT EXISTS contractors (
  id           VARCHAR(40)  NOT NULL PRIMARY KEY,
  slug         VARCHAR(140) NOT NULL UNIQUE,
  full_name    VARCHAR(200) NOT NULL,
  -- Their headline trade. The detail goes in skills.
  trade        VARCHAR(80)  NOT NULL,
  headline     VARCHAR(200) NOT NULL,
  about        TEXT         NULL,
  email        VARCHAR(320) NOT NULL,
  phone        VARCHAR(40)  NULL,
  city         VARCHAR(120) NOT NULL,
  years        INT UNSIGNED NULL,

  /*
   * Nothing is listed until somebody at Afrostrength has checked it.
   *
   * The whole value of a directory is that being on it means something. An
   * open directory is a phone book, and Afrostrength's name is on this one —
   * a bad introduction costs the company the next client, not the contractor.
   */
  state        ENUM('pending','verified','suspended','withdrawn') NOT NULL DEFAULT 'pending',
  verified_at  DATETIME     NULL,
  verified_by  VARCHAR(120) NULL,
  -- Why they were suspended, in words the contractor could be shown.
  state_note   VARCHAR(500) NULL,

  created_at   DATETIME     NOT NULL,
  updated_at   DATETIME     NOT NULL,
  INDEX idx_contractors_state (state, trade),
  INDEX idx_contractors_city (city),
  INDEX idx_contractors_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contractor_skills (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contractor_id VARCHAR(40) NOT NULL,
  skill         VARCHAR(80) NOT NULL,
  UNIQUE KEY uq_contractor_skill (contractor_id, skill),
  INDEX idx_skill (skill),
  CONSTRAINT fk_skills_contractor
    FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A client with something that needs doing.
CREATE TABLE IF NOT EXISTS jobs (
  id            VARCHAR(40)  NOT NULL PRIMARY KEY,
  ref           VARCHAR(24)  NOT NULL UNIQUE,
  client_name   VARCHAR(200) NOT NULL,
  client_email  VARCHAR(320) NOT NULL,
  client_phone  VARCHAR(40)  NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT         NOT NULL,
  trade         VARCHAR(80)  NOT NULL,
  city          VARCHAR(120) NOT NULL,
  /*
   * A band, not a number.
   *
   * A client who has not priced the work before cannot give a figure, and
   * asking for one either stops them posting or produces a number nobody
   * believes. A band is enough for a contractor to decide whether to bother.
   */
  budget_band   VARCHAR(60)  NULL,
  state         ENUM('open','introducing','filled','closed') NOT NULL DEFAULT 'open',
  -- The client's contact details are NOT shown until an introduction is made.
  -- Otherwise the directory is a free lead list and Afrostrength is a
  -- charity.
  closed_reason VARCHAR(500) NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  INDEX idx_jobs_state (state, created_at),
  INDEX idx_jobs_trade (trade, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A contractor putting themselves forward.
CREATE TABLE IF NOT EXISTS job_responses (
  id            VARCHAR(40) NOT NULL PRIMARY KEY,
  job_id        VARCHAR(40) NOT NULL,
  contractor_id VARCHAR(40) NOT NULL,
  message       TEXT        NOT NULL,
  created_at    DATETIME    NOT NULL,
  -- One response per contractor per job. A second is an edit, not enthusiasm.
  UNIQUE KEY uq_response (job_id, contractor_id),
  INDEX idx_responses_job (job_id, created_at),
  CONSTRAINT fk_responses_job
    FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE,
  CONSTRAINT fk_responses_contractor
    FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
 * The introduction. This is the product.
 *
 * Afrostrength's fee is for putting a named contractor in front of a named
 * client. The row records that it happened, who did it, and what was charged
 * — in integer kobo, like every other amount in this company's software.
 *
 * No escrow, and no money moves through this system. Holding other people's
 * funds in Nigeria raises CBN licensing questions that are a legal matter
 * rather than an engineering one. The client pays the contractor directly and
 * Afrostrength invoices its fee.
 */
CREATE TABLE IF NOT EXISTS introductions (
  id            VARCHAR(40) NOT NULL PRIMARY KEY,
  job_id        VARCHAR(40) NOT NULL,
  contractor_id VARCHAR(40) NOT NULL,
  fee_kobo      BIGINT      NOT NULL DEFAULT 0,
  state         ENUM('made','invoiced','paid','waived','void') NOT NULL DEFAULT 'made',
  reference     VARCHAR(120) NULL,
  note          VARCHAR(500) NULL,
  made_at       DATETIME    NOT NULL,
  made_by       VARCHAR(120) NOT NULL,
  settled_at    DATETIME    NULL,
  settled_by    VARCHAR(120) NULL,
  UNIQUE KEY uq_introduction (job_id, contractor_id),
  INDEX idx_introductions_state (state, made_at),
  CONSTRAINT fk_introductions_job
    FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE,
  CONSTRAINT fk_introductions_contractor
    FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE,
  CONSTRAINT chk_introduction_fee CHECK (fee_kobo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff. Same shape as the academy's, and deliberately a SEPARATE table in a
-- separate database: somebody who verifies contractors has no business in
-- learner records, and the surest way to guarantee that is not to share the
-- accounts.
CREATE TABLE IF NOT EXISTS staff (
  id            VARCHAR(40)  NOT NULL PRIMARY KEY,
  email         VARCHAR(320) NOT NULL UNIQUE,
  name          VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','agent') NOT NULL DEFAULT 'agent',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL,
  last_seen_at  DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_signin_attempts (
  id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(320) NOT NULL,
  ip    VARCHAR(45)  NOT NULL,
  ok    TINYINT(1)   NOT NULL,
  at    DATETIME     NOT NULL,
  INDEX idx_attempts_email (email, at),
  INDEX idx_attempts_ip (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  name       VARCHAR(60)  NOT NULL PRIMARY KEY,
  value      VARCHAR(255) NOT NULL,
  updated_at DATETIME     NOT NULL,
  updated_by VARCHAR(120) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The standard introduction fee, in kobo. Zero until somebody sets it: the
-- software must not invent what Afrostrength charges.
INSERT INTO settings (name, value, updated_at, updated_by)
VALUES ('introduction_fee_kobo', '0', NOW(), 'migration')
ON DUPLICATE KEY UPDATE name = name;
