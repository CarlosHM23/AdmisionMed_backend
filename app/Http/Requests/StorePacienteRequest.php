<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePacienteRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() != null;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'nullable|date',
            'tipo_documento' => 'nullable|string|max:50',
            'dni' => 'required|string|size:8|unique:pacientes,dni',
            'HistoriaClinica' => 'required|string|unique:pacientes,HistoriaClinica',
            'telefono' => 'nullable|string|max:9',
            'email' => 'nullable|email|max:255|unique:pacientes',
            'direccion' => 'required|string|max:100',
            'gestante' => 'nullable|boolean',
            'etapa_vida' => 'nullable|string|max:50',
            'detalle_gestante' => 'nullable|string|max:50',
        ];
    }
}
