<?php
declare(strict_types=1);

require_once __DIR__ . '/analytics-bootstrap.php';

const JG_BLOG_TIMEZONE = 'Asia/Jakarta';

function jg_blog_topics(): array
{
    return [
        'healthy-eating' => ['label' => 'Healthy Eating', 'accent' => '#9dff00'],
        'keeping-fit' => ['label' => 'Keeping Fit', 'accent' => '#22d3ee'],
        'losing-weight' => ['label' => 'Losing Weight', 'accent' => '#ffd400'],
        'diabetes-remission' => ['label' => 'Diabetes Remission', 'accent' => '#a78bfa'],
    ];
}

function jg_blog_statuses(): array
{
    return [
        'draft' => 'Draft',
        'in_review' => 'In review',
        'scheduled' => 'Scheduled',
        'archived' => 'Archived',
    ];
}

function jg_blog_fonts(): array
{
    return [
        'editorial' => 'ZERO Editorial',
        'modern' => 'Modern Sans',
        'classic' => 'Classic Serif',
        'humanist' => 'Humanist Sans',
    ];
}

function jg_blog_db(bool $ensureSchema = true): PDO
{
    static $pdo = null;
    static $schemaEnsured = false;
    if (!$pdo instanceof PDO) {
        $pdo = analyticsFreshDb();
    }
    if ($ensureSchema && !$schemaEnsured) {
        jg_blog_ensure_schema($pdo);
        $schemaEnsured = true;
    }
    return $pdo;
}

function jg_blog_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_blog_assets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            original_name VARCHAR(240) NOT NULL,
            mime_type VARCHAR(80) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL,
            width_px INT UNSIGNED NOT NULL DEFAULT 0,
            height_px INT UNSIGNED NOT NULL DEFAULT 0,
            alt_text VARCHAR(240) NOT NULL DEFAULT "",
            image_data MEDIUMBLOB NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_zero_blog_assets_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_blog_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(240) NOT NULL,
            slug VARCHAR(220) NOT NULL,
            excerpt TEXT NOT NULL,
            body_html LONGTEXT NOT NULL,
            body_text LONGTEXT NOT NULL,
            topic VARCHAR(40) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT "draft",
            author VARCHAR(120) NOT NULL DEFAULT "ZERO Editorial",
            seo_title VARCHAR(240) NOT NULL DEFAULT "",
            seo_description VARCHAR(320) NOT NULL DEFAULT "",
            featured_asset_id BIGINT UNSIGNED NULL DEFAULT NULL,
            scheduled_at DATETIME NULL DEFAULT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_zero_blog_posts_slug (slug),
            KEY idx_zero_blog_posts_status_schedule (status, scheduled_at),
            KEY idx_zero_blog_posts_topic_updated (topic, updated_at),
            KEY idx_zero_blog_posts_asset (featured_asset_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_blog_revisions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED NOT NULL,
            version INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_zero_blog_revision (post_id, version),
            KEY idx_zero_blog_revisions_post (post_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_blog_preview_links (
            post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            share_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_zero_blog_preview_token (share_token),
            KEY idx_zero_blog_preview_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_blog_post_styles (
            post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            font_key VARCHAR(32) NOT NULL DEFAULT "editorial",
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS zero_blog_delivery (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            delivery_mode VARCHAR(16) NOT NULL DEFAULT "off",
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $delivery = $pdo->prepare(
        'INSERT IGNORE INTO zero_blog_delivery (id, delivery_mode, updated_at)
         VALUES (1, "off", :updated_at)'
    );
    $delivery->execute([':updated_at' => jg_blog_now()]);
}

function jg_blog_delivery_modes(): array
{
    return [
        'off' => 'Off',
        'sandbox' => 'Sandbox',
        'live' => 'Live',
    ];
}

function jg_blog_delivery(PDO $pdo): array
{
    try {
        $row = $pdo->query('SELECT delivery_mode, updated_at FROM zero_blog_delivery WHERE id = 1 LIMIT 1')->fetch();
    } catch (PDOException) {
        $row = false;
    }
    $mode = is_array($row) ? strtolower((string) ($row['delivery_mode'] ?? 'off')) : 'off';
    if (!isset(jg_blog_delivery_modes()[$mode])) {
        $mode = 'off';
    }
    return [
        'mode' => $mode,
        'label' => jg_blog_delivery_modes()[$mode],
        'sandbox_url' => 'https://zerofoods.id/articles/sandbox/',
        'public_url' => 'https://zerofoods.id/articles/',
        'updated_at' => is_array($row) && !empty($row['updated_at'])
            ? gmdate(DATE_ATOM, strtotime((string) $row['updated_at'] . ' UTC'))
            : null,
    ];
}

function jg_blog_set_delivery(PDO $pdo, string $mode): array
{
    $mode = strtolower(trim($mode));
    if (!isset(jg_blog_delivery_modes()[$mode])) {
        throw new InvalidArgumentException('Choose Off, Sandbox, or Live delivery.');
    }
    $stmt = $pdo->prepare(
        'INSERT INTO zero_blog_delivery (id, delivery_mode, updated_at)
         VALUES (1, :delivery_mode, :updated_at)
         ON DUPLICATE KEY UPDATE delivery_mode = VALUES(delivery_mode), updated_at = VALUES(updated_at)'
    );
    $stmt->execute([':delivery_mode' => $mode, ':updated_at' => jg_blog_now()]);
    return jg_blog_delivery($pdo);
}

function jg_blog_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function jg_blog_slugify(string $value): string
{
    $value = trim(mb_strtolower($value));
    if (class_exists('Transliterator')) {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        if (is_string($transliterated)) {
            $value = $transliterated;
        }
    }
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    return substr($value !== '' ? $value : 'untitled-article', 0, 200);
}

function jg_blog_unique_slug(PDO $pdo, string $requested, string $title, int $excludeId = 0): string
{
    $base = jg_blog_slugify($requested !== '' ? $requested : $title);
    $candidate = $base;
    $suffix = 2;
    $stmt = $pdo->prepare('SELECT id FROM zero_blog_posts WHERE slug = :slug AND id <> :id LIMIT 1');
    while (true) {
        $stmt->execute([':slug' => $candidate, ':id' => $excludeId]);
        if ($stmt->fetchColumn() === false) {
            return $candidate;
        }
        $candidate = substr($base, 0, 194) . '-' . $suffix;
        $suffix++;
    }
}

function jg_blog_clean_text(mixed $value, int $maxLength): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    return mb_substr($text, 0, $maxLength);
}

function jg_blog_safe_href(string $href): string
{
    $href = trim($href);
    if ($href === '' || str_starts_with($href, '#')) {
        return $href;
    }
    $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https', 'mailto'], true) ? $href : '';
}

function jg_blog_safe_asset_src(string $src): string
{
    $parts = parse_url(trim($src));
    if (!is_array($parts) || !empty($parts['scheme']) || !empty($parts['host'])) {
        return '';
    }
    $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
    if (rtrim($path, '/') !== '/api/blogs') {
        return '';
    }
    parse_str((string) ($parts['query'] ?? ''), $query);
    $id = (int) ($query['id'] ?? 0);
    if (($query['action'] ?? '') !== 'asset' || $id < 1) {
        return '';
    }
    return '/api/blogs/?action=asset&id=' . $id;
}

function jg_blog_youtube_id(mixed $value): string
{
    $value = trim((string) $value);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1) {
        return $value;
    }
    $parts = parse_url($value);
    if (!is_array($parts)) {
        return '';
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    $host = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
    $pathParts = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/'))));
    $candidate = '';
    if ($host === 'youtu.be') {
        $candidate = (string) ($pathParts[0] ?? '');
    } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        parse_str((string) ($parts['query'] ?? ''), $query);
        $candidate = trim((string) ($query['v'] ?? ''));
        if ($candidate === '' && in_array((string) ($pathParts[0] ?? ''), ['embed', 'shorts', 'live'], true)) {
            $candidate = (string) ($pathParts[1] ?? '');
        }
    }
    return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1 ? $candidate : '';
}

function jg_blog_youtube_watch_url(string $videoId): string
{
    $videoId = jg_blog_youtube_id($videoId);
    return $videoId !== '' ? 'https://www.youtube.com/watch?v=' . $videoId : '';
}

function jg_blog_safe_youtube_thumbnail_src(string $src): string
{
    if (preg_match('~^https://i\.ytimg\.com/vi/([A-Za-z0-9_-]{11})/hqdefault\.jpg$~i', trim($src), $matches) !== 1) {
        return '';
    }
    return 'https://i.ytimg.com/vi/' . $matches[1] . '/hqdefault.jpg';
}

function jg_blog_sanitize_html(mixed $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="jg-blog-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $document->getElementById('jg-blog-root');
    if (!$root) {
        return '';
    }

    $allowed = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'blockquote', 'br', 'figure', 'div', 'img', 'figcaption', 'span'];
    $walk = static function (DOMNode $parent) use (&$walk, $allowed): void {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;
            if ($node instanceof DOMComment) {
                $parent->removeChild($node);
                $node = $next;
                continue;
            }
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, $allowed, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button'], true)) {
                        $parent->removeChild($node);
                    } else {
                        $walk($node);
                        while ($node->firstChild) {
                            $parent->insertBefore($node->firstChild, $node);
                        }
                        $parent->removeChild($node);
                    }
                    $node = $next;
                    continue;
                }

                $originalHref = $tag === 'a' ? $node->getAttribute('href') : '';
                $originalSrc = $tag === 'img' ? $node->getAttribute('src') : '';
                $originalAlt = $tag === 'img' ? $node->getAttribute('alt') : '';
                $originalScale = $tag === 'figure' ? $node->getAttribute('data-scale') : '';
                $originalShape = $tag === 'figure' ? $node->getAttribute('data-shape') : '';
                $originalWidth = $tag === 'figure' ? $node->getAttribute('data-width') : '';
                $originalAlign = $tag === 'figure' ? $node->getAttribute('data-align') : '';
                $originalAspect = $tag === 'figure' ? $node->getAttribute('data-aspect') : '';
                $originalCropTop = $tag === 'figure' ? $node->getAttribute('data-crop-top') : '';
                $originalCropRight = $tag === 'figure' ? $node->getAttribute('data-crop-right') : '';
                $originalCropBottom = $tag === 'figure' ? $node->getAttribute('data-crop-bottom') : '';
                $originalCropLeft = $tag === 'figure' ? $node->getAttribute('data-crop-left') : '';
                $originalYoutubeId = $tag === 'figure' ? $node->getAttribute('data-youtube-id') : '';
                $originalYoutubeWidth = $tag === 'figure' ? $node->getAttribute('data-youtube-width') : '';
                $originalYoutubeAlign = $tag === 'figure' ? $node->getAttribute('data-youtube-align') : '';
                $isImageFrame = $tag === 'div' && $node->hasAttribute('data-image-frame');
                $isYoutubeThumbnail = $tag === 'div' && $node->hasAttribute('data-youtube-thumbnail');
                $isYoutubeTrigger = $tag === 'a' && $node->hasAttribute('data-youtube-trigger');
                $isYoutubeLink = $tag === 'a' && $node->hasAttribute('data-youtube-link');
                $isYoutubePlay = $tag === 'span' && $node->hasAttribute('data-youtube-play');
                $isYoutubeLabel = $tag === 'span' && $node->hasAttribute('data-youtube-label');
                $isYoutubePlatform = $tag === 'span' && $node->hasAttribute('data-youtube-platform');
                $walk($node);
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $node->removeAttribute($attribute->name);
                }
                if ($tag === 'a') {
                    $safeHref = jg_blog_safe_href($originalHref);
                    if ($safeHref !== '') {
                        $node->setAttribute('href', $safeHref);
                        if (str_starts_with($safeHref, 'http')) {
                            $node->setAttribute('rel', 'noopener noreferrer');
                        }
                        $youtubeId = jg_blog_youtube_id($safeHref);
                        if ($youtubeId !== '' && ($isYoutubeTrigger || $isYoutubeLink)) {
                            $node->setAttribute('href', jg_blog_youtube_watch_url($youtubeId));
                            $node->setAttribute($isYoutubeTrigger ? 'data-youtube-trigger' : 'data-youtube-link', '');
                            $node->setAttribute('target', '_blank');
                        }
                    }
                }
                if ($tag === 'img') {
                    $safeSrc = jg_blog_safe_asset_src($originalSrc);
                    if ($safeSrc === '') {
                        $safeSrc = jg_blog_safe_youtube_thumbnail_src($originalSrc);
                    }
                    if ($safeSrc === '') {
                        $parent->removeChild($node);
                        $node = $next;
                        continue;
                    }
                    $node->setAttribute('src', $safeSrc);
                    $node->setAttribute('alt', jg_blog_clean_text($originalAlt, 240));
                    $node->setAttribute('loading', 'lazy');
                    $node->setAttribute('decoding', 'async');
                }
                if ($tag === 'div' && $isImageFrame) {
                    $node->setAttribute('data-image-frame', '');
                }
                if ($tag === 'div' && $isYoutubeThumbnail) {
                    $node->setAttribute('data-youtube-thumbnail', '');
                }
                if ($tag === 'span' && $isYoutubePlay) {
                    $node->setAttribute('data-youtube-play', '');
                }
                if ($tag === 'span' && $isYoutubeLabel) {
                    $node->setAttribute('data-youtube-label', '');
                }
                if ($tag === 'span' && $isYoutubePlatform) {
                    $node->setAttribute('data-youtube-platform', '');
                }
                if ($tag === 'figure') {
                    $youtubeId = jg_blog_youtube_id($originalYoutubeId);
                    if ($youtubeId !== '') {
                        $youtubeWidth = max(10, min(100, (int) $originalYoutubeWidth ?: 100));
                        $youtubeAlign = in_array($originalYoutubeAlign, ['left', 'center', 'right'], true) ? $originalYoutubeAlign : 'center';
                        $node->setAttribute('data-youtube-id', $youtubeId);
                        $node->setAttribute('data-youtube-width', (string) $youtubeWidth);
                        $node->setAttribute('data-youtube-align', $youtubeAlign);
                        $node->setAttribute('style', '--youtube-width:' . $youtubeWidth . '%');
                        $node = $next;
                        continue;
                    }
                    $scale = (int) $originalScale;
                    $legacyScale = in_array($scale, [40, 50, 60, 70, 80, 90, 100], true) ? $scale : 100;
                    $width = $originalWidth !== '' ? (int) $originalWidth : $legacyScale;
                    $width = max(25, min(100, $width));
                    $align = in_array($originalAlign, ['left', 'center', 'right'], true) ? $originalAlign : 'center';
                    $aspect = (float) $originalAspect;
                    $aspect = $aspect >= 0.1 && $aspect <= 10 ? $aspect : 1.5;
                    $cropTop = max(0.0, min(45.0, (float) $originalCropTop));
                    $cropRight = max(0.0, min(45.0, (float) $originalCropRight));
                    $cropBottom = max(0.0, min(45.0, (float) $originalCropBottom));
                    $cropLeft = max(0.0, min(45.0, (float) $originalCropLeft));
                    if ($cropLeft + $cropRight > 75) {
                        $cropRight = 75 - $cropLeft;
                    }
                    if ($cropTop + $cropBottom > 75) {
                        $cropBottom = 75 - $cropTop;
                    }
                    $visibleX = (100 - $cropLeft - $cropRight) / 100;
                    $visibleY = (100 - $cropTop - $cropBottom) / 100;
                    $formatNumber = static fn (float $value): string => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
                    $node->setAttribute('data-width', (string) $width);
                    $node->setAttribute('data-align', $align);
                    $node->setAttribute('data-aspect', $formatNumber($aspect));
                    $node->setAttribute('data-crop-top', $formatNumber($cropTop));
                    $node->setAttribute('data-crop-right', $formatNumber($cropRight));
                    $node->setAttribute('data-crop-bottom', $formatNumber($cropBottom));
                    $node->setAttribute('data-crop-left', $formatNumber($cropLeft));
                    $node->setAttribute('style', implode(';', [
                        '--figure-width:' . $width . '%',
                        '--crop-aspect:' . $formatNumber($aspect * $visibleX / $visibleY),
                        '--crop-image-width:' . $formatNumber(100 / $visibleX) . '%',
                        '--crop-image-height:' . $formatNumber(100 / $visibleY) . '%',
                        '--crop-image-left:' . $formatNumber(-$cropLeft / $visibleX) . '%',
                        '--crop-image-top:' . $formatNumber(-$cropTop / $visibleY) . '%',
                    ]));
                    $node->setAttribute('data-scale', (string) $legacyScale);
                    $node->setAttribute('data-shape', in_array($originalShape, ['original', 'landscape', 'square', 'portrait'], true) ? $originalShape : 'original');
                }
            }
            $node = $next;
        }
    };
    $walk($root);

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }
    return trim($result);
}

function jg_blog_plain_text(string $html): string
{
    $text = html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/[\t ]+|\R{3,}/u', ' ', $text) ?? '');
}

function jg_blog_word_count(string $text): int
{
    if ($text === '') {
        return 0;
    }
    preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $text, $matches);
    return count($matches[0] ?? []);
}

function jg_blog_parse_wib_datetime(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timezone = new DateTimeZone(JG_BLOG_TIMEZONE);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone)
        ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException('Choose a valid schedule date and time.');
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function jg_blog_wib_value(?string $utcValue): ?string
{
    if (!$utcValue) {
        return null;
    }
    return (new DateTimeImmutable($utcValue, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(JG_BLOG_TIMEZONE))
        ->format('Y-m-d\TH:i');
}

function jg_blog_validate_payload(array $payload, bool $forRestore = false): array
{
    $topics = jg_blog_topics();
    $statuses = jg_blog_statuses();
    $fonts = jg_blog_fonts();
    $title = jg_blog_clean_text($payload['title'] ?? '', 240);
    $excerpt = jg_blog_clean_text($payload['excerpt'] ?? '', 400);
    $bodyHtml = jg_blog_sanitize_html($payload['body_html'] ?? '');
    $bodyText = jg_blog_plain_text($bodyHtml);
    $topic = strtolower(trim((string) ($payload['topic'] ?? 'healthy-eating')));
    $status = strtolower(trim((string) ($payload['status'] ?? 'draft')));
    $author = jg_blog_clean_text($payload['author'] ?? 'ZERO Editorial', 120);
    $scheduledAt = jg_blog_parse_wib_datetime($payload['scheduled_at'] ?? '');
    $assetId = max(0, (int) ($payload['featured_asset_id'] ?? 0));
    $fontKey = strtolower(trim((string) ($payload['font_key'] ?? 'editorial')));

    if (!isset($topics[$topic])) {
        throw new InvalidArgumentException('Choose one of the four ZERO article topics.');
    }
    if (!isset($statuses[$status])) {
        throw new InvalidArgumentException('Choose a valid editorial status.');
    }
    if (!isset($fonts[$fontKey])) {
        throw new InvalidArgumentException('Choose a supported article font.');
    }
    if ($status === 'in_review' && ($title === '' || jg_blog_word_count($bodyText) < 30)) {
        throw new InvalidArgumentException('Add a title and at least 30 words before sending an article to review.');
    }
    if ($status === 'scheduled') {
        if (mb_strlen($title) < 8 || mb_strlen($excerpt) < 40 || jg_blog_word_count($bodyText) < 100 || mb_strlen($author) < 2) {
            throw new InvalidArgumentException('To schedule, add a clear title, a 40-character summary, an author, and at least 100 words.');
        }
        if ($scheduledAt === null) {
            throw new InvalidArgumentException('Choose a WIB date and time before scheduling.');
        }
        if (!$forRestore && (int) ($payload['id'] ?? 0) === 0 && strtotime($scheduledAt . ' UTC') < time() + 60) {
            throw new InvalidArgumentException('Schedule the article at least one minute in the future.');
        }
    }

    return [
        'title' => $title !== '' ? $title : 'Untitled article',
        'slug' => jg_blog_slugify((string) ($payload['slug'] ?? $title)),
        'excerpt' => $excerpt,
        'body_html' => $bodyHtml,
        'body_text' => $bodyText,
        'topic' => $topic,
        'status' => $status,
        'author' => $author !== '' ? $author : 'ZERO Editorial',
        'seo_title' => jg_blog_clean_text($payload['seo_title'] ?? '', 240),
        'seo_description' => jg_blog_clean_text($payload['seo_description'] ?? '', 320),
        'featured_asset_id' => $assetId > 0 ? $assetId : null,
        'scheduled_at' => $scheduledAt,
        'font_key' => $fontKey,
    ];
}

function jg_blog_effective_state(array $row, ?DateTimeImmutable $now = null): string
{
    if (($row['status'] ?? '') === 'scheduled' && !empty($row['scheduled_at']) && strtotime((string) $row['scheduled_at'] . ' UTC') <= ($now?->getTimestamp() ?? time())) {
        return 'ready';
    }
    return (string) ($row['status'] ?? 'draft');
}

function jg_blog_format_post(array $row, bool $includeBody = true): array
{
    $bodyText = (string) ($row['body_text'] ?? '');
    $wordCount = jg_blog_word_count($bodyText);
    $assetId = (int) ($row['featured_asset_id'] ?? 0);
    $previewToken = strtolower(trim((string) ($row['share_token'] ?? '')));
    $previewEnabled = jg_blog_valid_share_token($previewToken);
    $post = [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'slug' => (string) $row['slug'],
        'excerpt' => (string) $row['excerpt'],
        'topic' => (string) $row['topic'],
        'status' => (string) $row['status'],
        'effective_status' => jg_blog_effective_state($row),
        'author' => (string) $row['author'],
        'seo_title' => (string) $row['seo_title'],
        'seo_description' => (string) $row['seo_description'],
        'font_key' => isset(jg_blog_fonts()[(string) ($row['font_key'] ?? '')]) ? (string) $row['font_key'] : 'editorial',
        'featured_asset_id' => $assetId ?: null,
        'featured_image_url' => $assetId ? '/api/blogs/?action=asset&id=' . $assetId : null,
        'scheduled_at' => jg_blog_wib_value($row['scheduled_at'] ?? null),
        'scheduled_at_utc' => $row['scheduled_at'] ? gmdate(DATE_ATOM, strtotime((string) $row['scheduled_at'] . ' UTC')) : null,
        'version' => (int) $row['version'],
        'word_count' => $wordCount,
        'reading_minutes' => max(1, (int) ceil($wordCount / 220)),
        'preview_enabled' => $previewEnabled,
        'preview_path' => $previewEnabled ? '/blog-preview/?token=' . $previewToken : null,
        'created_at' => gmdate(DATE_ATOM, strtotime((string) $row['created_at'] . ' UTC')),
        'updated_at' => gmdate(DATE_ATOM, strtotime((string) $row['updated_at'] . ' UTC')),
    ];
    if ($includeBody) {
        $post['body_html'] = (string) $row['body_html'];
        $post['body_text'] = $bodyText;
    }
    return $post;
}

function jg_blog_post(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT posts.*, previews.share_token, COALESCE(styles.font_key, "editorial") AS font_key
         FROM zero_blog_posts posts
         LEFT JOIN zero_blog_preview_links previews ON previews.post_id = posts.id
         LEFT JOIN zero_blog_post_styles styles ON styles.post_id = posts.id
         WHERE posts.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new OutOfBoundsException('Article not found.');
    }
    return jg_blog_format_post($row);
}

function jg_blog_list(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT posts.*, previews.share_token, COALESCE(styles.font_key, "editorial") AS font_key
         FROM zero_blog_posts posts
         LEFT JOIN zero_blog_preview_links previews ON previews.post_id = posts.id
         LEFT JOIN zero_blog_post_styles styles ON styles.post_id = posts.id
         ORDER BY posts.updated_at DESC, posts.id DESC LIMIT 500'
    )->fetchAll();
    return array_map(static fn (array $row): array => jg_blog_format_post($row, false), $rows);
}

function jg_blog_valid_share_token(string $token): bool
{
    return preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
}

function jg_blog_enable_preview(PDO $pdo, int $postId, bool $rotate = false): array
{
    jg_blog_post($pdo, $postId);
    $stmt = $pdo->prepare('SELECT share_token FROM zero_blog_preview_links WHERE post_id = :post_id LIMIT 1');
    $stmt->execute([':post_id' => $postId]);
    $existing = strtolower(trim((string) $stmt->fetchColumn()));
    if (!$rotate && jg_blog_valid_share_token($existing)) {
        return jg_blog_post($pdo, $postId);
    }

    $now = jg_blog_now();
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $token = bin2hex(random_bytes(32));
        try {
            $save = $pdo->prepare(
                'INSERT INTO zero_blog_preview_links (post_id, share_token, created_at, updated_at)
                 VALUES (:post_id, :share_token, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE share_token = VALUES(share_token), updated_at = VALUES(updated_at)'
            );
            $save->execute([
                ':post_id' => $postId,
                ':share_token' => $token,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            return jg_blog_post($pdo, $postId);
        } catch (PDOException $error) {
            if ((string) $error->getCode() !== '23000' || $attempt === 2) {
                throw $error;
            }
        }
    }
    throw new RuntimeException('A private preview link could not be created.');
}

function jg_blog_disable_preview(PDO $pdo, int $postId): array
{
    jg_blog_post($pdo, $postId);
    $stmt = $pdo->prepare('DELETE FROM zero_blog_preview_links WHERE post_id = :post_id');
    $stmt->execute([':post_id' => $postId]);
    return jg_blog_post($pdo, $postId);
}

function jg_blog_shared_post(PDO $pdo, string $token): array
{
    $token = strtolower(trim($token));
    if (!jg_blog_valid_share_token($token)) {
        throw new OutOfBoundsException('Preview not found.');
    }
    $stmt = $pdo->prepare(
        'SELECT posts.*, previews.share_token, COALESCE(styles.font_key, "editorial") AS font_key
         FROM zero_blog_preview_links previews
         INNER JOIN zero_blog_posts posts ON posts.id = previews.post_id
         LEFT JOIN zero_blog_post_styles styles ON styles.post_id = posts.id
         WHERE previews.share_token = :share_token LIMIT 1'
    );
    $stmt->execute([':share_token' => $token]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new OutOfBoundsException('Preview not found.');
    }
    return jg_blog_format_post($row);
}

function jg_blog_post_asset_ids(array $post): array
{
    $ids = [];
    $featuredId = (int) ($post['featured_asset_id'] ?? 0);
    if ($featuredId > 0) {
        $ids[$featuredId] = true;
    }
    preg_match_all('~/api/blogs/\\?action=asset(?:&amp;|&)id=(\\d+)~i', (string) ($post['body_html'] ?? ''), $matches);
    foreach ($matches[1] ?? [] as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = true;
        }
    }
    return array_map('intval', array_keys($ids));
}

function jg_blog_public_body_html(string $html, string $token): string
{
    $token = strtolower(trim($token));
    if (!jg_blog_valid_share_token($token)) {
        return '';
    }
    $safeHtml = jg_blog_sanitize_html($html);
    return (string) preg_replace_callback(
        '~src="/api/blogs/\\?action=asset(?:&amp;|&)id=(\\d+)"~i',
        static fn (array $match): string => 'src="/blog-preview/asset.php?token=' . $token . '&amp;id=' . (int) $match[1] . '"',
        $safeHtml
    );
}

function jg_blog_stats(array $posts): array
{
    $stats = ['total' => 0, 'draft' => 0, 'in_review' => 0, 'scheduled' => 0, 'ready' => 0, 'archived' => 0];
    foreach ($posts as $post) {
        $stats['total']++;
        $key = (string) ($post['effective_status'] ?? $post['status'] ?? 'draft');
        if (array_key_exists($key, $stats)) {
            $stats[$key]++;
        }
    }
    return $stats;
}

function jg_blog_snapshot(array $row): array
{
    return array_intersect_key($row, array_flip([
        'title', 'slug', 'excerpt', 'body_html', 'body_text', 'topic', 'status', 'author',
        'seo_title', 'seo_description', 'featured_asset_id', 'scheduled_at', 'font_key',
    ]));
}

function jg_blog_write_revision(PDO $pdo, array $row): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO zero_blog_revisions (post_id, version, snapshot_json, created_at)
         VALUES (:post_id, :version, :snapshot_json, :created_at)'
    );
    $stmt->execute([
        ':post_id' => (int) $row['id'],
        ':version' => (int) $row['version'],
        ':snapshot_json' => json_encode(jg_blog_snapshot($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ':created_at' => jg_blog_now(),
    ]);
}

function jg_blog_save(PDO $pdo, array $payload): array
{
    $id = max(0, (int) ($payload['id'] ?? 0));
    $expectedVersion = max(0, (int) ($payload['version'] ?? 0));
    $values = jg_blog_validate_payload($payload);
    $fontKey = (string) $values['font_key'];
    unset($values['font_key']);
    if ($values['featured_asset_id'] !== null) {
        $asset = $pdo->prepare('SELECT id FROM zero_blog_assets WHERE id = :id LIMIT 1');
        $asset->execute([':id' => $values['featured_asset_id']]);
        if ($asset->fetchColumn() === false) {
            throw new InvalidArgumentException('The selected cover image is no longer available.');
        }
    }

    $pdo->beginTransaction();
    try {
        $now = jg_blog_now();
        if ($id === 0) {
            $values['slug'] = jg_blog_unique_slug($pdo, $values['slug'], $values['title']);
            $stmt = $pdo->prepare(
                'INSERT INTO zero_blog_posts
                 (title, slug, excerpt, body_html, body_text, topic, status, author, seo_title, seo_description,
                  featured_asset_id, scheduled_at, version, created_at, updated_at)
                 VALUES
                 (:title, :slug, :excerpt, :body_html, :body_text, :topic, :status, :author, :seo_title, :seo_description,
                  :featured_asset_id, :scheduled_at, 1, :created_at, :updated_at)'
            );
            $stmt->execute($values + [':created_at' => $now, ':updated_at' => $now]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $select = $pdo->prepare(
                'SELECT posts.*, COALESCE(styles.font_key, "editorial") AS font_key
                 FROM zero_blog_posts posts
                 LEFT JOIN zero_blog_post_styles styles ON styles.post_id = posts.id
                 WHERE posts.id = :id FOR UPDATE'
            );
            $select->execute([':id' => $id]);
            $current = $select->fetch();
            if (!is_array($current)) {
                throw new OutOfBoundsException('Article not found.');
            }
            if ($expectedVersion !== (int) $current['version']) {
                throw new UnexpectedValueException('This article changed in another browser. Reload it before saving again.');
            }

            $values['slug'] = jg_blog_unique_slug($pdo, $values['slug'], $values['title'], $id);
            $currentSnapshot = jg_blog_snapshot($current);
            $changed = false;
            foreach ($values + ['font_key' => $fontKey] as $key => $value) {
                if (($currentSnapshot[$key] ?? null) != $value) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                $pdo->commit();
                return jg_blog_post($pdo, $id);
            }

            jg_blog_write_revision($pdo, $current);
            $stmt = $pdo->prepare(
                'UPDATE zero_blog_posts SET
                    title = :title, slug = :slug, excerpt = :excerpt, body_html = :body_html, body_text = :body_text,
                    topic = :topic, status = :status, author = :author, seo_title = :seo_title,
                    seo_description = :seo_description, featured_asset_id = :featured_asset_id,
                    scheduled_at = :scheduled_at, version = version + 1, updated_at = :updated_at
                 WHERE id = :id AND version = :expected_version'
            );
            $stmt->execute($values + [':updated_at' => $now, ':id' => $id, ':expected_version' => $expectedVersion]);
            if ($stmt->rowCount() !== 1) {
                throw new UnexpectedValueException('This article changed while it was saving. Reload it before trying again.');
            }
        }
        $style = $pdo->prepare(
            'INSERT INTO zero_blog_post_styles (post_id, font_key, updated_at)
             VALUES (:post_id, :font_key, :updated_at)
             ON DUPLICATE KEY UPDATE font_key = VALUES(font_key), updated_at = VALUES(updated_at)'
        );
        $style->execute([':post_id' => $id, ':font_key' => $fontKey, ':updated_at' => $now]);
        $pdo->commit();
        return jg_blog_post($pdo, $id);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function jg_blog_archive(PDO $pdo, int $id, int $expectedVersion): array
{
    $post = jg_blog_post($pdo, $id);
    $post['status'] = 'archived';
    $post['scheduled_at'] = null;
    $post['version'] = $expectedVersion;
    return jg_blog_save($pdo, $post);
}

function jg_blog_duplicate(PDO $pdo, int $id): array
{
    $source = jg_blog_post($pdo, $id);
    unset($source['id']);
    $source['version'] = 0;
    $source['title'] = jg_blog_clean_text($source['title'] . ' — Copy', 240);
    $source['slug'] = jg_blog_slugify($source['slug'] . '-copy');
    $source['status'] = 'draft';
    $source['scheduled_at'] = null;
    return jg_blog_save($pdo, $source);
}

function jg_blog_revisions(PDO $pdo, int $postId): array
{
    $stmt = $pdo->prepare('SELECT id, version, created_at FROM zero_blog_revisions WHERE post_id = :post_id ORDER BY version DESC LIMIT 30');
    $stmt->execute([':post_id' => $postId]);
    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'version' => (int) $row['version'],
        'created_at' => gmdate(DATE_ATOM, strtotime((string) $row['created_at'] . ' UTC')),
    ], $stmt->fetchAll());
}

function jg_blog_restore_revision(PDO $pdo, int $postId, int $revisionId, int $expectedVersion): array
{
    $stmt = $pdo->prepare('SELECT snapshot_json FROM zero_blog_revisions WHERE id = :id AND post_id = :post_id LIMIT 1');
    $stmt->execute([':id' => $revisionId, ':post_id' => $postId]);
    $snapshot = json_decode((string) $stmt->fetchColumn(), true);
    if (!is_array($snapshot)) {
        throw new OutOfBoundsException('Revision not found.');
    }
    $snapshot['id'] = $postId;
    $snapshot['version'] = $expectedVersion;
    $snapshot['scheduled_at'] = jg_blog_wib_value($snapshot['scheduled_at'] ?? null);
    return jg_blog_save($pdo, $snapshot);
}

function jg_blog_store_asset(PDO $pdo, array $upload, string $altText = ''): array
{
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? 'The cover image is larger than the server upload limit.'
            : 'Choose an image to upload.');
    }
    $size = (int) ($upload['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Cover images must be 5 MB or smaller.');
    }
    $path = (string) ($upload['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($path);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        throw new InvalidArgumentException('Upload a JPEG, PNG, WebP, or GIF image.');
    }
    $dimensions = @getimagesize($path);
    if (!is_array($dimensions) || (int) $dimensions[0] < 320 || (int) $dimensions[1] < 180) {
        throw new InvalidArgumentException('Cover images must be at least 320 × 180 pixels.');
    }
    $data = file_get_contents($path);
    if (!is_string($data)) {
        throw new RuntimeException('The uploaded image could not be read.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO zero_blog_assets
         (original_name, mime_type, size_bytes, width_px, height_px, alt_text, image_data, created_at)
         VALUES (:original_name, :mime_type, :size_bytes, :width_px, :height_px, :alt_text, :image_data, :created_at)'
    );
    $stmt->bindValue(':original_name', jg_blog_clean_text($upload['name'] ?? 'cover-image', 240));
    $stmt->bindValue(':mime_type', $mime);
    $stmt->bindValue(':size_bytes', $size, PDO::PARAM_INT);
    $stmt->bindValue(':width_px', (int) $dimensions[0], PDO::PARAM_INT);
    $stmt->bindValue(':height_px', (int) $dimensions[1], PDO::PARAM_INT);
    $stmt->bindValue(':alt_text', jg_blog_clean_text($altText, 240));
    $stmt->bindValue(':image_data', $data, PDO::PARAM_LOB);
    $stmt->bindValue(':created_at', jg_blog_now());
    $stmt->execute();
    $id = (int) $pdo->lastInsertId();
    return [
        'id' => $id,
        'url' => '/api/blogs/?action=asset&id=' . $id,
        'mime_type' => $mime,
        'size_bytes' => $size,
        'width' => (int) $dimensions[0],
        'height' => (int) $dimensions[1],
    ];
}

function jg_blog_asset(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM zero_blog_assets WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new OutOfBoundsException('Image not found.');
    }
    return $row;
}

function jg_blog_delivery_posts(PDO $pdo, bool $sandbox = false): array
{
    $where = $sandbox
        ? 'posts.status = "scheduled"'
        : 'posts.status = "scheduled" AND posts.scheduled_at IS NOT NULL AND posts.scheduled_at <= UTC_TIMESTAMP()';
    $rows = $pdo->query(
        'SELECT posts.*, COALESCE(styles.font_key, "editorial") AS font_key, "" AS share_token
         FROM zero_blog_posts posts
         LEFT JOIN zero_blog_post_styles styles ON styles.post_id = posts.id
         WHERE ' . $where . '
         ORDER BY posts.scheduled_at DESC, posts.id DESC LIMIT 500'
    )->fetchAll();
    return array_map(static fn (array $row): array => jg_blog_format_post($row, true), $rows);
}

function jg_blog_delivery_post(PDO $pdo, string $slug, bool $sandbox = false): array
{
    $slug = jg_blog_slugify($slug);
    $where = $sandbox
        ? 'posts.status = "scheduled"'
        : 'posts.status = "scheduled" AND posts.scheduled_at IS NOT NULL AND posts.scheduled_at <= UTC_TIMESTAMP()';
    $stmt = $pdo->prepare(
        'SELECT posts.*, COALESCE(styles.font_key, "editorial") AS font_key, "" AS share_token
         FROM zero_blog_posts posts
         LEFT JOIN zero_blog_post_styles styles ON styles.post_id = posts.id
         WHERE posts.slug = :slug AND ' . $where . ' LIMIT 1'
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new OutOfBoundsException('Article not found.');
    }
    return jg_blog_format_post($row, true);
}

function jg_blog_delivery_asset_allowed(array $post, int $assetId): bool
{
    return $assetId > 0 && in_array($assetId, jg_blog_post_asset_ids($post), true);
}
