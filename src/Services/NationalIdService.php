<?php
declare(strict_types=1);

namespace PersianGravityForms\Services;

use PersianGravityForms\Contracts\Hookable;
use PersianGravityForms\Helpers\Sanitize;

/**
 * اعتبارسنجی کد ملی ایران در Gravity Forms
 */
class NationalIdService implements Hookable
{
    public function register_hooks(): void
    {
        add_filter('gform_field_validation', [$this, 'validate_national_id'], 10, 4);
    }

    /**
     * اعتبارسنجی کد ملی
     */
    public function validate_national_id($result, $value, $form, $field)
    {
        if ($field->type !== 'national_id') {
            return $result;
        }
        $value = Sanitize::text($value);
        if (!$this->is_valid_national_id($value)) {
            $result['is_valid'] = false;
            $result['message'] = __('کد ملی وارد شده معتبر نیست.', 'persian-gravity-forms');
        }
        return $result;
    }

    /**
     * الگوریتم اعتبارسنجی کد ملی ایران
     */
    private function is_valid_national_id(string $code): bool
    {
        if (!preg_match('/^\d{10}$/', $code)) {
            return false;
        }
        $check = (int) $code[9];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $mod = $sum % 11;
        return ($mod < 2 && $check == $mod) || ($mod >= 2 && $check == 11 - $mod);
    }
} 