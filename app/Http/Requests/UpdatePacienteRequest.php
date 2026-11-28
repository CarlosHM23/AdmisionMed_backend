<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePacienteRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() != null;
    }

    public function rules()
    {
        $id = $this->route('id') ?? $this->route('paciente');
        return [
            'nombre' => 'sometimes|required|string|max:255',
            'apellido' => 'sometimes|required|string|max:255',
            'genero' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'nullable|date',
            'tipo_documento' => 'nullable|string|max:50',
            'dni' => "sometimes|required|string|size:8|unique:pacientes,dni,$id",
            'HistoriaClinica' => "required|string|unique:pacientes,HistoriaClinica,$id",
            'telefono' => 'nullable|string|max:9',
            'email' => "nullable|email|max:255|unique:pacientes,email,$id",
            'direccion' => 'sometimes|required|string|max:100',
            'gestante' => 'nullable|boolean',
            'etapa_vida' => 'nullable|string|max:50',
            'detalle_gestante' => 'nullable|string|max:50',
            'estado' => 'nullable|string|max:50',
        ];
    }
}
