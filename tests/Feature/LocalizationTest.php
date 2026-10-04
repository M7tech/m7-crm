<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_visitor_can_switch_to_arabic_with_rtl_layout(): void
    {
        $this->from(route('home'))->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('locale', 'ar');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('نظام CRM عملي لفرق المبيعات العراقية');
    }

    public function test_visitor_can_switch_to_sorani_with_rtl_layout(): void
    {
        $this->post(route('locale.update'), ['locale' => 'ckb'])
            ->assertSessionHas('locale', 'ckb');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('lang="ckb"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('CRMێکی کرداری بۆ تیمەکانی فرۆشتنی عێراق');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->from(route('home'))->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('locale');
    }
}
