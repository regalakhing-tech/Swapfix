<?php
declare(strict_types=1);

namespace SwapFix\Application;

/** Shared markup for headings, form fields, dialogs and small action forms. */
trait UiComponents
{
    public function heading(string $icon, string $title, string $subtitle = '', string $actions = ''): string
    {
        $this->title = $title;
        return '<header class="page-heading"><span class="icon-tile">' . $this->icon($icon) . '</span><div class="page-heading-text"><h1>' . $this->e($title) . '</h1>'
            . ($subtitle !== '' ? '<p>' . $this->e($subtitle) . '</p>' : '') . '</div>'
            . ($actions !== '' ? '<div class="page-actions">' . $actions . '</div>' : '') . '</header>';
    }

    /** The welcome banner at the top of a dashboard. */
    public function dashHero(string $icon, string $badge, string $title, string $subtitle, string $actions = '', string $pageTitle = ''): string
    {
        $this->title = $pageTitle !== '' ? $pageTitle : $title;
        return '<section class="dash-hero"><div class="dash-hero-copy"><span class="dash-pill">' . $this->icon($icon) . $this->e($badge) . '</span><h1>' . $this->e($title) . '</h1><p>' . $this->e($subtitle) . '</p></div>'
            . ($actions !== '' ? '<div class="dash-hero-actions">' . $actions . '</div>' : '') . '</section>';
    }

    /** A dashboard number. $share is [part, whole] and draws a progress bar without inline styles, which the CSP blocks. */
    public function kpi(string $icon, int|string $value, string $label, string $tone, string $href, ?array $share = null): string
    {
        $bar = $share === null ? '' : '<progress value="' . (int) $share[0] . '" max="' . max(1, (int) $share[1]) . '" aria-label="' . $this->e($label) . ' share of jobs"></progress>';
        return '<a class="kpi kpi-' . $this->e($tone) . '" href="' . $this->e($href) . '"><span class="kpi-head"><span class="kpi-label">' . $this->e($label) . '</span><span class="kpi-icon">' . $this->icon($icon) . '</span></span><strong class="kpi-value">' . $this->e($value) . '</strong>' . $bar . '</a>';
    }

    public function link(string $href, string $label, string $icon = '', string $class = 'button button-primary', array $attributes = []): string
    {
        return '<a class="' . $this->e($class) . '" href="' . $this->e($href) . '"' . $this->attributes($attributes) . '>' . ($icon !== '' ? $this->icon($icon) : '') . $this->e($label) . '</a>';
    }

    public function dialogLink(string $dialog, string $param, string $label, string $icon = '', string $class = 'button button-primary'): string
    {
        return $this->link($this->url([$param => '1']), $label, $icon, $class, ['data-open-dialog' => $dialog]);
    }

    public function alert(?string $message, string $tone = 'error'): string
    {
        if ($message === null || $message === '') {
            return '';
        }
        return '<div class="alert alert-' . $this->e($tone) . '" role="' . ($tone === 'error' ? 'alert' : 'status') . '">' . $this->icon($tone === 'error' ? 'alert' : 'info') . '<span>' . $this->e($message) . '</span></div>';
    }

    public function emptyState(string $icon, string $message, string $action = ''): string
    {
        return '<div class="empty-state"><span class="icon-tile">' . $this->icon($icon) . '</span><p>' . $this->e($message) . '</p>' . $action . '</div>';
    }

    public function input(string $name, string $label, mixed $value = '', array $attributes = [], string $hint = ''): string
    {
        $id = 'field-' . $name;
        return $this->field($id, $label, '<input id="' . $this->e($id) . '" name="' . $this->e($name) . '" value="' . $this->e($value) . '"' . $this->attributes($attributes) . '>', $hint, $attributes);
    }

    public function textarea(string $name, string $label, mixed $value = '', array $attributes = [], string $hint = ''): string
    {
        $id = 'field-' . $name;
        return $this->field($id, $label, '<textarea id="' . $this->e($id) . '" name="' . $this->e($name) . '"' . $this->attributes($attributes + ['rows' => 4]) . '>' . $this->e($value) . '</textarea>', $hint, $attributes);
    }

    public function select(string $name, string $label, array $options, mixed $selected = '', array $attributes = [], string $hint = ''): string
    {
        $id = 'field-' . $name;
        $html = '';
        foreach ($options as $value => $text) {
            $html .= '<option value="' . $this->e($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . $this->e($text) . '</option>';
        }
        return $this->field($id, $label, '<select id="' . $this->e($id) . '" name="' . $this->e($name) . '"' . $this->attributes($attributes) . '>' . $html . '</select>', $hint, $attributes);
    }

    public function photoInput(string $name, string $label, int $max): string
    {
        $multiple = $max > 1;
        return '<div class="field field-full"><span class="field-label">' . $this->e($label) . '</span><label class="photo-drop">' . $this->icon('camera')
            . '<span>Choose ' . ($multiple ? 'up to ' . $max . ' photos' : 'a photo') . '</span><small>JPEG, PNG or WebP, up to 4 MiB total per form</small>'
            . '<input class="sr-only" type="file" name="' . $this->e($name . ($multiple ? '[]' : '')) . '" accept="image/jpeg,image/png,image/webp" data-photo-input' . ($multiple ? ' multiple' : '') . '></label>'
            . '<div class="photo-preview" data-photo-preview></div></div>';
    }

    public function dialogStart(string $id, string $title, string $param): string
    {
        $close = $this->url([$param => null]);
        return '<dialog class="detail-dialog form-dialog" id="' . $this->e($id) . '" aria-labelledby="' . $this->e($id) . '-title" data-url-param="' . $this->e($param) . '"' . (isset($this->query[$param]) ? ' open' : '') . '>'
            . '<a class="icon-button dialog-close" href="' . $this->e($close) . '" data-close-dialog aria-label="Close">' . $this->icon('close') . '</a>'
            . '<div class="dialog-body"><h2 id="' . $this->e($id) . '-title">' . $this->e($title) . '</h2>';
    }

    public function dialogEnd(): string
    {
        return '</div></dialog>';
    }

    /** A one-button POST form. $confirm is [title, message, action label]. */
    public function postButton(string $action, string $label, string $icon = '', string $class = 'button button-outline', array $hidden = [], array $confirm = []): string
    {
        $html = '<form method="post" action="' . $this->e($action) . '" class="inline-form"';
        if ($confirm !== []) {
            $html .= ' data-confirm-title="' . $this->e($confirm[0]) . '" data-confirm="' . $this->e($confirm[1]) . '" data-confirm-action="' . $this->e($confirm[2]) . '"';
        }
        $html .= '>' . $this->csrf();
        foreach ($hidden as $name => $value) {
            $html .= '<input type="hidden" name="' . $this->e($name) . '" value="' . $this->e($value) . '">';
        }
        return $html . '<button class="' . $this->e($class) . '" type="submit">' . ($icon !== '' ? $this->icon($icon) : '') . $this->e($label) . '</button></form>';
    }

    public function pagination(int $total, int $page, int $size): string
    {
        $pages = (int) ceil($total / max(1, $size));
        if ($pages <= 1) {
            return '';
        }
        $html = '<nav class="pagination" aria-label="Pages">';
        if ($page > 1) {
            $html .= $this->link($this->url(['page' => $page - 1]), 'Previous', 'arrow-left', 'button button-outline');
        }
        $html .= '<span>Page ' . $page . ' of ' . $pages . '</span>';
        if ($page < $pages) {
            $html .= $this->link($this->url(['page' => $page + 1]), 'Next', 'arrow', 'button button-outline');
        }
        return $html . '</nav>';
    }

    private function field(string $id, string $label, string $control, string $hint, array $attributes): string
    {
        $wide = isset($attributes['data-wide']) ? ' field-full' : '';
        return '<div class="field' . $wide . '"><label for="' . $this->e($id) . '">' . $this->e($label) . (isset($attributes['required']) ? '' : ' <small>optional</small>') . '</label>' . $control
            . ($hint !== '' ? '<small class="field-hint">' . $this->e($hint) . '</small>' : '') . '</div>';
    }

    private function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $html .= ' ' . $this->e($name) . ($value === true ? '' : '="' . $this->e($value) . '"');
        }
        return $html;
    }
}
