<?php
declare(strict_types=1);

namespace PersianGravityForms\Services;

use PersianGravityForms\Contracts\Hookable;
use PersianGravityForms\Helpers\Sanitize;

/**
 * مدیریت فیلد آدرس در Gravity Forms
 */
class AddressService implements Hookable
{
    public function register_hooks(): void
    {
        add_filter('gform_field_input', [$this, 'render_address_field'], 10, 5);
        add_filter('gform_pre_submission_filter', [$this, 'sanitize_address_input']);
    }

    /**
     * رندر فیلد آدرس (نمونه ساده)
     */
    public function render_address_field($input, $field, $value, $lead_id, $form_id)
    {
        if ($field->type !== 'address') {
            return $input;
        }
        // نمونه ساده: می‌توانید اینجا HTML سفارشی تولید کنید
        return $input;
    }

    /**
     * پاک‌سازی ورودی آدرس
     */
    public function sanitize_address_input(array $form): array
    {
        foreach ($form['fields'] as &$field) {
            if ($field->type === 'address' && isset($_POST['input_' . $field->id])) {
                $_POST['input_' . $field->id] = Sanitize::text($_POST['input_' . $field->id]);
            }
        }
        return $form;
    }
} 