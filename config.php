<?php
// ============================================================
//  IluminusTech — Configuração do Supabase
// ============================================================
// Preencha com os dados do seu projeto em:
//   https://supabase.com/dashboard/project/<SEU_PROJETO>/settings/api
// ============================================================

define('SUPABASE_URL',          'https://lthkfxzvuzpyyqhbufso.supabase.co');
define('SUPABASE_ANON_KEY',     'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imx0aGtmeHp2dXpweXlxaGJ1ZnNvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY4OTAyNDYsImV4cCI6MjA5MjQ2NjI0Nn0.DVTS9Z0bew_uDxJgzEGIjjd2bp2DE1hSbqYkwcxo1ZQ');
define('SUPABASE_SERVICE_KEY',  'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imx0aGtmeHp2dXpweXlxaGJ1ZnNvIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3Njg5MDI0NiwiZXhwIjoyMDkyNDY2MjQ2fQ.t1iZ7RB8WctTKSBuII3mT-yJB4rxcxKHZ-OL0u65Czs');   // nunca exponha no frontend

// ── Brevo (envio de e-mail) ──────────────────────────────────
define('BREVO_API_KEY',  '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');
define('BREVO_SENDER_NAME',  'IluminusTech');
define('BREVO_SENDER_EMAIL', 'michel_walendolf@estudante.sesisenai.org.br');

// ── hCaptcha ────────────────────────────────────────────────
define('HCAPTCHA_SECRET', 'ES_5f0cff6ae80643388ee12d6b609cf55f');

// ── Sessão ──────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200);   // 2 horas em segundos
define('SESSION_NAME',     'iluminustech_sess');
