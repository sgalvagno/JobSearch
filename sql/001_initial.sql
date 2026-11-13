-- Initial installation only. MariaDB 10.11+, InnoDB, UTC.
SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE TABLE companies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(255) NOT NULL, legal_name VARCHAR(255), siren CHAR(9) UNIQUE,
 website_url TEXT, domain VARCHAR(255), linkedin_url TEXT,
 company_type ENUM('direct_employer','esn','recruitment_agency','unknown') NOT NULL DEFAULT 'unknown',
 industry VARCHAR(255), summary TEXT, presentation_text MEDIUMTEXT,
 presentation_source_url TEXT, presentation_collected_at DATETIME,
 size_range VARCHAR(80), address_line VARCHAR(255), postal_code VARCHAR(30), city VARCHAR(150), country_code CHAR(2),
 latitude DECIMAL(10,7), longitude DECIMAL(10,7), contact_email VARCHAR(254), phone VARCHAR(80),
 careers_url TEXT, spontaneous_application_url TEXT, information_source_url TEXT, last_verified_at DATETIME,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_company_name(name), INDEX idx_company_domain(domain), INDEX idx_company_type(company_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE company_preferences (
 company_id BIGINT UNSIGNED PRIMARY KEY,
 visibility ENUM('normal','deprioritized','excluded') NOT NULL DEFAULT 'normal',
 interest_level ENUM('preferred','neutral','low') NOT NULL DEFAULT 'neutral', reason TEXT, notes TEXT,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_preferences_company FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE company_aliases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 alias VARCHAR(255) NOT NULL, normalized_alias VARCHAR(255) NOT NULL, source VARCHAR(255),
 UNIQUE(company_id,normalized_alias), FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE company_contacts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 first_name VARCHAR(150), last_name VARCHAR(150), job_title VARCHAR(255), department VARCHAR(255),
 email VARCHAR(254), phone VARCHAR(80), linkedin_url TEXT, profile_url TEXT,
 contact_type ENUM('person','recruitment','general') NOT NULL DEFAULT 'person', source_url TEXT, verified_at DATETIME, notes TEXT,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 deleted_at DATETIME, FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE job_offers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NULL, recruiter_company_id BIGINT UNSIGNED NULL,
 title VARCHAR(255) NOT NULL, description MEDIUMTEXT, requirements_text MEDIUMTEXT, benefits_text TEXT,
 contract_type VARCHAR(50), employment_type VARCHAR(50), location_text VARCHAR(255), city VARCHAR(150), postal_code VARCHAR(30), country_code CHAR(2),
 remote_policy ENUM('unknown','onsite','hybrid','remote') NOT NULL DEFAULT 'unknown', remote_days_per_week DECIMAL(3,1),
 salary_min DECIMAL(12,2), salary_max DECIMAL(12,2), salary_currency CHAR(3), salary_period ENUM('hour','month','year'),
 experience_min_years DECIMAL(4,1), seniority VARCHAR(80), education_level VARCHAR(255),
 published_at DATETIME, expires_at DATETIME, first_seen_at DATETIME, last_seen_at DATETIME, last_checked_at DATETIME,
 status ENUM('unknown','active','expired','closed') NOT NULL DEFAULT 'unknown', status_reason TEXT, closed_at DATETIME,
 application_url TEXT, application_email VARCHAR(254),
 review_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending', data_confidence DECIMAL(4,3),
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME,
 FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(recruiter_company_id) REFERENCES companies(id),
 INDEX idx_offer_status_city(status,city), INDEX idx_offer_seen(last_seen_at),
 CHECK(salary_min IS NULL OR salary_max IS NULL OR salary_min<=salary_max),
 CHECK(data_confidence IS NULL OR data_confidence BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE job_offer_sources (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, job_offer_id BIGINT UNSIGNED NOT NULL,
 source_name VARCHAR(100) NOT NULL, external_id VARCHAR(255), source_url TEXT NOT NULL,
 source_url_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_checked_at DATETIME,
 source_status ENUM('unknown','active','expired','closed') NOT NULL DEFAULT 'unknown', content_hash CHAR(64),
 UNIQUE(source_name,external_id), FOREIGN KEY(job_offer_id) REFERENCES job_offers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE job_offer_tracking (
 job_offer_id BIGINT UNSIGNED PRIMARY KEY, is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
 viewed_at DATETIME, dismissed_at DATETIME, dismiss_reason TEXT, personal_notes TEXT,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(job_offer_id) REFERENCES job_offers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
 job_offer_id BIGINT UNSIGNED, contact_id BIGINT UNSIGNED,
 type ENUM('job_offer','spontaneous') NOT NULL,
 status ENUM('draft','sent','acknowledged','interview','offer_received','rejected','withdrawn','no_response') NOT NULL DEFAULT 'draft',
 channel VARCHAR(80), applied_at DATETIME, next_follow_up_at DATETIME, cv_reference VARCHAR(255), cover_letter_reference VARCHAR(255), notes TEXT,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(job_offer_id) REFERENCES job_offers(id), FOREIGN KEY(contact_id) REFERENCES company_contacts(id),
 CHECK((type='spontaneous' AND job_offer_id IS NULL) OR (type='job_offer' AND job_offer_id IS NOT NULL)),
 CHECK(status='draft' OR applied_at IS NOT NULL), INDEX idx_followup(next_follow_up_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE application_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(80) NOT NULL, occurred_at DATETIME NOT NULL, notes TEXT,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(application_id) REFERENCES applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE api_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, token_prefix VARCHAR(20) NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 scopes JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME, last_used_at DATETIME, revoked_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE import_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL,
 external_batch_id VARCHAR(150) NOT NULL UNIQUE, payload_hash CHAR(64) NOT NULL,
 result JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(client_id) REFERENCES api_tokens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
