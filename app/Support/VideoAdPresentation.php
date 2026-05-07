<?php

namespace App\Support;

class VideoAdPresentation
{
    public const ALLOWED_HTML_TAGS = '<a><b><strong><i><em><u><br><span><div><img><p><h3><h4><ul><li><ol>';

    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $trimmed = mb_substr($html, 0, 12000);

        return strip_tags($trimmed, self::ALLOWED_HTML_TAGS);
    }

    public static function resolved(): array
    {
        $topOn = PlatformConfig::get('video_ad_banner_top_enabled', '0') === '1';
        $bottomOn = PlatformConfig::get('video_ad_banner_bottom_enabled', '0') === '1';

        $popOn = PlatformConfig::get('video_ad_pop_enabled', '0') === '1';
        $popTpl = PlatformConfig::get('video_ad_pop_template', 'none');
        $popCustom = (string) PlatformConfig::getText('video_ad_pop_custom_html', '');
        $popDelay = max(0, min(120000, (int) PlatformConfig::get('video_ad_pop_delay_ms', '3500')));
        $popTitle = PlatformConfig::get('video_ad_pop_title', 'Información');

        $color = self::menuColorHex();

        return [
            'banner_top_enabled' => $topOn,
            'banner_top_html' => $topOn ? self::resolveSlotBanner('top', $color) : '',
            'banner_bottom_enabled' => $bottomOn,
            'banner_bottom_html' => $bottomOn ? self::resolveSlotBanner('bottom', $color) : '',
            'pop_enabled' => $popOn,
            'pop_delay_ms' => $popDelay,
            'pop_title' => $popTitle,
            'pop_body_html' => $popOn ? self::popBody($popTpl, $popCustom, $color) : '',
        ];
    }

    private static function resolveSlotBanner(string $position, string $color): string
    {
        $modeKey = $position === 'top' ? 'video_ad_banner_top_mode' : 'video_ad_banner_bottom_mode';
        $libKey = $position === 'top' ? 'video_ad_banner_top_library_id' : 'video_ad_banner_bottom_library_id';
        $scriptKey = $position === 'top' ? 'video_ad_banner_top_custom_script' : 'video_ad_banner_bottom_custom_script';
        $script = self::rawScript((string) PlatformConfig::getText($scriptKey, ''));
        $mode = PlatformConfig::get($modeKey, 'legacy');
        if ($mode !== 'library') {
            $tplKey = $position === 'top' ? 'video_ad_banner_top_template' : 'video_ad_banner_bottom_template';
            $customKey = $position === 'top' ? 'video_ad_banner_top_custom_html' : 'video_ad_banner_bottom_custom_html';
            $tpl = PlatformConfig::get($tplKey, 'none');
            $custom = (string) PlatformConfig::getText($customKey, '');
            $html = self::bannerHtml((string) $tpl, $custom, $position, $color);
            if ($script !== '') {
                $html .= '<div class="eda-video-ad-script eda-video-ad-script--' . $position . '">' . $script . '</div>';
            }

            return $html;
        }

        $id = trim((string) PlatformConfig::get($libKey, ''));
        if ($id === '') {
            return '';
        }
        $tpl = BannerTemplateRegistry::findById($id);
        if (!$tpl || empty($tpl['enabled'])) {
            return '';
        }
        $inner = self::sanitize((string) ($tpl['html'] ?? ''));
        $margin = $position === 'bottom' ? 'margin-top:10px;' : 'margin-bottom:10px;';

        $html = $inner === '' ? '' : '<div class="eda-video-ad eda-video-ad--library" style="' . $margin . '">' . $inner . '</div>';
        if ($script !== '') {
            $html .= '<div class="eda-video-ad-script eda-video-ad-script--' . $position . '">' . $script . '</div>';
        }

        return $html;
    }

    private static function menuColorHex(): string
    {
        $c = PlatformConfig::get('menu_color', '#d83a7c');
        if (is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c)) {
            return $c;
        }

        return '#d83a7c';
    }

    private static function bannerHtml(string $template, string $custom, string $position, string $color): string
    {
        $margin = $position === 'bottom' ? 'margin-top:10px;' : 'margin-bottom:10px;';

        if ($template === 'custom') {
            $inner = self::sanitize($custom);

            return $inner === '' ? '' : '<div class="eda-video-ad eda-video-ad--custom" style="' . $margin . '">' . $inner . '</div>';
        }

        if ($template === 'strip') {
            $safeColor = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');

            return '<div class="eda-video-ad eda-video-ad--strip" style="' . $margin . 'padding:9px 14px;border-radius:10px;background:linear-gradient(90deg,#f8fafc,#eef2ff);font-size:13px;color:#475569;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">'
                . '<span style="font-weight:600;letter-spacing:0.04em;text-transform:uppercase;font-size:11px;color:#94a3b8;">Patrocinado</span>'
                . '<a href="#" onclick="return false" style="color:' . $safeColor . ';font-weight:600;text-decoration:none;">Saber más</a>'
                . '</div>';
        }

        if ($template === 'cta') {
            $safeColor = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');

            return '<div class="eda-video-ad eda-video-ad--cta" style="' . $margin . 'padding:12px 14px;border-radius:12px;border:1px solid #e2e8f0;background:#fff;box-shadow:0 4px 14px rgba(15,23,42,0.06);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">'
                . '<span style="font-size:14px;color:#334155;font-weight:500;">Apoyá nuestro contenido</span>'
                . '<span style="display:inline-block;padding:6px 14px;border-radius:999px;background:' . $safeColor . ';color:#fff;font-size:12px;font-weight:600;">Ver ofertas</span>'
                . '</div>';
        }

        if ($template === 'badge') {
            return '<div class="eda-video-ad eda-video-ad--badge" style="' . $margin . 'display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
                . '<span style="font-size:11px;font-weight:700;letter-spacing:0.08em;color:#64748b;text-transform:uppercase;">Partner</span>'
                . '<span style="font-size:12px;color:#475569;">Contenido promocional opcional</span>'
                . '</div>';
        }

        return '';
    }

    private static function popBody(string $template, string $custom, string $color): string
    {
        if ($template === 'custom') {
            return self::sanitize($custom);
        }

        if ($template === 'simple') {
            $safeColor = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');

            return '<p style="margin:0 0 10px;font-size:14px;line-height:1.5;color:#475569;">'
                . 'Gracias por ver este contenido. Podés apoyar al creador compartiendo el enlace.'
                . '</p>'
                . '<a href="#" onclick="return false" style="display:inline-block;padding:8px 16px;border-radius:999px;background:' . $safeColor . ';color:#fff;font-size:13px;font-weight:600;text-decoration:none;">Entendido</a>';
        }

        return '';
    }

    private static function rawScript(string $script): string
    {
        if ($script === '') {
            return '';
        }
        // Se permite script sin sanitizar por requisito explícito del módulo de ads.
        return str_replace("\0", '', mb_substr($script, 0, 12000));
    }
}
