<?php
namespace Tests\Unit\Eir;

use App\Services\Eir\CalculateEirService;
use PHPUnit\Framework\TestCase;

class DateSensitiveEirServiceTest extends TestCase
{
    public function test_one_year_cash_flow_solves_effective_annual_rate(): void
    {
        $result=(new CalculateEirService())->calculateDated(1000,[
            ['due_date'=>'2026-01-01','amount'=>1100],
        ],12,'2025-01-01','ACT/365');
        $this->assertEqualsWithDelta(0.10,$result['eir_effective_annual'],1e-10);
        $this->assertSame('DATED_NEWTON_RAPHSON',$result['method']);
    }

    public function test_irregular_dates_use_actual_day_exponents(): void
    {
        $service=new CalculateEirService();
        $result=$service->calculateDated(1000,[
            ['due_date'=>'2025-04-01','amount'=>300],
            ['due_date'=>'2025-10-17','amount'=>780],
        ],12,'2025-01-01','ACT/365');
        $flows=$result['input_snapshot']['cash_flows'];
        $this->assertEqualsWithDelta(90/365,$flows[0]['exponent'],1e-12);
        $this->assertEqualsWithDelta(289/365,$flows[1]['exponent'],1e-12);
        $npv=-1000+300/pow(1+$result['eir_effective_annual'],$flows[0]['exponent'])
            +780/pow(1+$result['eir_effective_annual'],$flows[1]['exponent']);
        $this->assertEqualsWithDelta(0,$npv,1e-7);
    }
}
