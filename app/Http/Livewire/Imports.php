<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Import;
use Livewire\WithPagination;
use Carbon\Carbon;
use App\Models\Cover;
use App\Models\Detail;
use Exception;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ImportsImport;

class Imports extends Component
{
    use WithPagination;
    use WithFileUploads;

    public $description,$description_2,$amount,$amount_2,$search,$selected_id,$pageTitle,$componentName,$my_total,$details;
    public $from,$to,$cov,$cov_det;
    private $pagination = 20;
    public $data_to_import;

    public function paginationView(){

        return 'vendor.livewire.bootstrap';
    }

    public function mount(){

        $this->pageTitle = 'listado';
        $this->componentName = 'gastos de importacion';
        //$this->my_total = 0;
        $this->details = [];
        $this->from = Carbon::parse(Carbon::now())->format('Y-m-d') . ' 00:00:00';
        $this->to = Carbon::parse(Carbon::now())->format('Y-m-d') . ' 23:59:59';
        $this->cov = Cover::firstWhere('description',$this->componentName);
        $this->cov_det = $this->cov->details->where('cover_id',$this->cov->id)->whereBetween('created_at',[$this->from, $this->to])->first();
        $this->data_to_import = null;
    }

    public function render()
    {   
        $this->my_total = 0;

        if(strlen($this->search) > 0){

            $data = Import::where('description', 'like', '%' . $this->search . '%')
            ->paginate($this->pagination);

            $this->my_total = Import::where('description', 'like', '%' . $this->search . '%')
            ->sum('amount');

        }else{

            $data = Import::orderBy('id', 'asc')->paginate($this->pagination);

            $vars = Import::all();

            foreach($vars as $var){

                $this->my_total += $var->amount;
            }
            //$this->my_total = $this->cov->balance;
        }

        return view('livewire.import.imports', [

            'imports' => $data
        ])
        ->extends('layouts.theme.app')
        ->section('content');

    }

    public function Store(){

        if($this->cov_det != null){

            $rules = [

                'description' => 'required|min:10|max:255',
                'amount' => 'required|numeric'
            ];

            $messages = [

                'description.required' => 'La descripcion es requerida',
                'description.min' => 'La descripcion debe contener al menos 10 caracteres',
                'description.max' => 'La descripcion debe contener 255 caracteres como maximo',
                'amount.required' => 'El monto es requerido',
                'amount.numeric' => 'Este campo solo admite numeros'
            ];
            
            $this->validate($rules, $messages);

            DB::beginTransaction();
            
                try {

                    $import = Import::create([

                        'description' => $this->description,
                        'amount' => $this->amount
                    ]);

                    if($import){

                        $this->cov->update([
                        
                            'balance' => $this->cov->balance + $this->amount
                
                        ]);
                
                        $this->cov_det->update([
                
                            'ingress' => $this->cov_det->ingress + $this->amount,
                            'actual_balance' => $this->cov_det->actual_balance + $this->amount
                
                        ]);
                    }

                    DB::commit();
                    $this->emit('item-added', 'Registro Exitoso');
                    $this->resetUI();
                    $this->mount();
                    $this->render();

                } catch (Exception) {
                    
                    DB::rollback();
                    $this->emit('movement-error', 'Algo salio mal');
                }

        }else{

            $this->emit('cover-error','Se debe crear caratula del dia');
            return;
        }

    }

    public function Edit(Import $import)
    {
        $this->selected_id = $import->id;
        $this->description = $import->description;
        $this->amount = floatval($import->amount);
        $this->amount_2 = 0;
        $this->emit('show-modal2', 'Abrir Modal');
    }

    public function Update()
    {
        if (!$this->cov_det) {

            $this->emit('cover-error','Se debe crear caratula del dia.');
            return;

        } else {

            $rules = [

                'description' => 'required|min:10|max:255',
                'amount' => 'required|numeric',
                'amount_2' => 'required|numeric|gte:0|lte:amount',
                'description_2' => 'exclude_if:amount_2,0|required|min:10|max:255'

            ];

            $messages = [

                'description.required' => 'Campo requerido',
                'description.min' => 'Minimo 10 caracteres',
                'description.max' => 'Maximo 255 caracteres',
                'amount.required' => 'Campo requerido',
                'amount.numeric' => 'Este campo solo admite numeros',
                'amount_2.required' => 'Campo requerido',
                'amount_2.numeric' => 'Este campo solo admite numeros',
                'amount_2.gte' => 'El monto a pagar debe ser mayor o igual a 0',
                'amount_2.lte' => 'El monto a pagar debe ser menor o igual al saldo actual',
                'description_2.required' => 'Campo requerido',
                'description_2.min' => 'Minimo 10 caracteres',
                'description_2.max' => 'Maximo 255 caracteres',

            ];

            $this->validate($rules, $messages);

            DB::beginTransaction();
            
            try {

                $import = Import::find($this->selected_id);
            
                if ($this->amount_2 <= 0) {
                    
                    $import->Update([

                        'description' => $this->description

                    ]);

                } else {

                    $detail = $import->details()->create([

                        'description' => $this->description_2,
                        'amount' => $this->amount_2,
                        'previus_balance' => $import->amount,
                        'actual_balance' => $import->amount - $this->amount_2
                        
                    ]);

                    if (!$detail) {

                        $this->emit('movement-error', 'Error al registrar el detalle del movimiento.');
                        return;

                    } else {

                        $import->Update([

                            'description' => $this->description,
                            'amount' => $import->amount - $detail->amount

                        ]);

                        $this->cov->update([
                        
                            'balance' => $this->cov->balance - $detail->amount
                
                        ]);
                
                        $this->cov_det->update([
            
                            'egress' => $this->cov_det->egress + $detail->amount,
                            'actual_balance' => $this->cov_det->actual_balance - $detail->amount
            
                        ]);

                    }
                }

                DB::commit();
                $this->emit('item-updated', 'Registro Actualizado.');
                $this->resetUI();
                $this->mount();
                $this->render();

            } catch (Exception $e) {
                
                DB::rollback();
                //$this->emit('error-message', $e->getMessage());
                $this->emit('movement-error', 'Algo salio mal.');

            }
        }
    }

    protected $listeners = [
        
        'destroy' => 'Destroy',
        'cancel' => 'Cancel',
    ];

    public function Destroy(Import $import){

        if($this->cov_det != null){

            DB::beginTransaction();
            
                try {
        
                    $this->cov->update([
                        
                        'balance' => $this->cov->balance - $import->amount

                    ]);

                    $this->cov_det->update([

                        'ingress' => $this->cov_det->ingress - $import->amount,
                        'actual_balance' => $this->cov_det->actual_balance - $import->amount

                    ]);

                    $import->delete();
                    DB::commit();
                    $this->emit('item-deleted', 'Registro Eliminado');
                    $this->resetUI();
                    $this->mount();
                    $this->render();

                } catch (Exception) {
                    
                    DB::rollback();
                    $this->emit('movement-error', 'Algo salio mal');
                }

        }else{

            $this->emit('cover-error','Se debe crear caratula del dia');
            return;
        }
    }

    public function Details(Import $import){

        $this->details = $import->details;
        $this->emit('show-detail', 'Mostrando modal');
    }

    public function Cancel(Detail $det){

        if($this->cov_det != null){

            $import = Import::firstWhere('id',$det->detailable_id);

            DB::beginTransaction();
            
                try {

                    if(($det->actual_balance + $det->amount) == (number_format($import->amount,2) + $det->amount)){
                        
                        $import->update([
                    
                            'amount' => $import->amount + $det->amount
            
                        ]);
            
                        $this->cov->update([
                
                            'balance' => $this->cov->balance + $det->amount
            
                        ]);
            
                        $this->cov_det->update([
            
                            'egress' => $this->cov_det->egress - $det->amount,
                            'actual_balance' => $this->cov_det->actual_balance + $det->amount
            
                        ]);

                        $det->delete();
                        $this->emit('cancel-detail', 'Registro Anulado');

                    }else{

                        $this->emit('report-error', 'El saldo no coincide. Anule los movimientos mas recientes.');
                        return;
                    }

                    DB::commit();
                    $this->resetUI();
                    $this->mount();
                    $this->render();

                } catch (Exception) {
                    
                    DB::rollback();
                    $this->emit('report-error', 'Algo salio mal');
                }

        }else{

            $this->emit('cover-error','Se debe crear caratula del dia');
            return;
        }
        
    }

    public function ImportData(){

        $rules = [

            'data_to_import' => 'required|file|max:2048|mimes:csv,xls,xlsx'
        ];

        $messages = [

            'data_to_import.required' => 'Seleccione un archivo',
            'data_to_import.file' => 'Seleccione un archivo valido',
            'data_to_import.max' => 'Maximo 2 mb',
            'data_to_import.mimes' => 'Solo archivos excel'
        ];
        
        $this->validate($rules, $messages);

        try {

            Excel::import(new ImportsImport,$this->data_to_import);
            $this->emit('import-successfull','Carga de datos exitosa.');
            $this->resetUI();

        } catch (\Exception $e) {

            $this->emit('movement-error', 'Error al cargar datos.');
            return;

        }

    }

    public function resetUI(){

        $this->description = '';
        $this->description_2 = '';
        $this->amount = '';
        $this->amount_2 = 0;
        $this->search = '';
        $this->selected_id = 0;
        $this->data_to_import = null;
        $this->resetValidation();
        $this->resetPage();
    }
}
