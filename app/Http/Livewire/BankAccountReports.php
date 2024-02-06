<?php

namespace App\Http\Livewire;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Cover;
use App\Models\Detail;
use App\Models\Paydesk;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class BankAccountReports extends Component
{   

    public $componentName,$details,$reportRange,$bank_account_id,$now,$dateFrom,$dateTo,$date_field_1,$date_field_2;

    public function mount()
    {
        $this->componentName = 'MOVIMIENTOS BANCARIOS';
        $this->details = [];
        $this->reportRange = 0;
        $this->bank_account_id = 0;
        $this->date_field_1 = '';
        $this->date_field_2 = '';
        $this->now = Carbon::now();
        $this->dateFrom = $this->now->format('Y-m-d') . ' 00:00:00';
        $this->dateTo = $this->now->format('Y-m-d') . ' 23:59:59';
    }

    public function render()
    {   
        $this->ReportsByDate();

        return view('livewire.bank_account_report.bank-account-reports', [

            'bank_accounts' => BankAccount::with('company','bank')->get()
        ])
        ->extends('layouts.theme.app')
        ->section('content');
    }

    public function ReportsByDate()
    {
        if ($this->reportRange == 0) {

            $from = $this->now->format('Y-m-d') . ' 00:00:00';
            $to = $this->now->format('Y-m-d') . ' 23:59:59';

        } else {

            $from = $this->date_field_1. ' 00:00:00';
            $to = $this->date_field_2. ' 23:59:59';

        }

        if ($this->reportRange == 1 && ($this->date_field_1 == '' || $this->date_field_2 == '')) {

            $this->emit('report-error', 'Seleccione fecha de inicio y fecha de fin');
            $this->details = [];
            return;
        }

        if ($this->bank_account_id == 0) {

            /*$this->details = BankAccount::join('details as d','d.detailable_id','bank_accounts.id')
            ->select('d.*')
            ->whereBetween('d.created_at', [$from, $to])
            ->where('d.detailable_type','App\Models\BankAccount')
            ->orderBy('d.detailable_id')->get();*/

            $this->details = Detail::whereBetween('created_at', [$from, $to])
                ->where('detailable_type','App\Models\BankAccount')
                ->orderBy('detailable_id')
                ->get();

        } else {
            
            /*$this->details = BankAccount::join('details as d','d.detailable_id','bank_accounts.id')
                ->select('d.*')
                ->whereBetween('d.created_at', [$from, $to])
                ->where('d.detailable_id',$this->bank_account_id)
                ->where('d.detailable_type','App\Models\BankAccount')
                ->orderBy('d.id')
                ->get();*/

            $this->details = Detail::whereBetween('created_at', [$from, $to])
                ->where('detailable_type','App\Models\BankAccount')
                ->where('detailable_id',$this->bank_account_id)
                ->orderBy('id')
                ->get();

        }
    }

    protected $listeners = [

        'destroy' => 'Destroy'
        
    ];

    public function Destroy(Detail $det)
    {
        $account = BankAccount::firstWhere('id',$det->detailable_id);
        $company = Company::firstWhere('id',$account->company_id)->description;
        $bank = Bank::firstWhere('id',$account->bank_id)->description;
        $cov = Cover::firstWhere('description',$bank . ' ' . $account->type . ' ' . $account->currency . ' ' . $company);
        $cov_det = $cov->details->where('cover_id',$cov->id)->whereBetween('created_at',[$this->dateFrom, $this->dateTo])->first();

        if($det->actual_balance > $det->previus_balance){

            if(($det->actual_balance - $det->amount) == ($account->amount - $det->amount)){

                $account->update([
                
                    'amount' => $account->amount - $det->amount

                ]);

                $cov->update([

                    'balance' => $cov->balance - $det->amount

                ]);

                $cov_det->update([

                    'ingress' => $cov_det->ingress - $det->amount,
                    'actual_balance' => $cov_det->actual_balance - $det->amount

                ]);

                $det->delete();
                $this->emit('report-error', 'Movimiento Anulado.');

            }else{

                $this->emit('report-error', 'El saldo no coincide. Anule los movimientos mas recientes.');
                return;
            }

        }else{

            if(($det->actual_balance + $det->amount) == ($account->amount + $det->amount)){
                
                $account->update([
            
                    'amount' => $account->amount + $det->amount
    
                ]);
    
                $cov->update([
        
                    'balance' => $cov->balance + $det->amount
    
                ]);
    
                $cov_det->update([
    
                    'egress' => $cov_det->egress - $det->amount,
                    'actual_balance' => $cov_det->actual_balance + $det->amount
    
                ]);

                $det->delete();
                $this->emit('report-error', 'Movimiento Anulado.');

            }else{

                $this->emit('report-error', 'El saldo no coincide. Anule los movimientos mas recientes.');
                return;
            }
        }

        $this->mount();
        $this->render();
    }

}
