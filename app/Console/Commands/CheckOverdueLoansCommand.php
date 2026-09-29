<?php

namespace App\Console\Commands;

use App\Services\LoanService;
use Illuminate\Console\Command;

class CheckOverdueLoansCommand extends Command
{
    protected $signature = 'loans:check-overdue';

    protected $description = 'Marca los préstamos activos de más de 2 meses como vencidos';

    public function handle(LoanService $loans): int
    {
        $flagged = $loans->flagOverdueLoans();

        $this->info(trans_choice(':count préstamo marcado como vencido.|:count préstamos marcados como vencidos.', $flagged));

        return self::SUCCESS;
    }
}
