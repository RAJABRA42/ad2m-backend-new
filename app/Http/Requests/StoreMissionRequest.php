<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreMissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return  Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
       return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'lieu_depart' => ['required', 'string', 'max:100'],
            'lieu_destination' => ['required', 'string', 'max:100'],
            'date_depart' => ['required', 'date', 'after_or_equal:today'],
            'date_retour' => ['required', 'date', 'after_or_equal:date_depart'],
            
            // Le chef de projet (CP) est assigné manuellement lors de la création
            // Il doit exister dans la table 'users' et avoir le rôle 'chef_de_projet'
            'validation_cp_id' => [
                'required', 
                'exists:users,id',
                // Rule::exists('users', 'id')->where(function ($query) {
                //     return $query->where('role', 'chef_de_projet');
                // }),
            ],
            
            'motif' => ['required', 'string', 'max:255'],
            'moyen_transport' => ['required', 'string', 'in:avion,train,voiture,autre'],
            'budget_estime' => ['required', 'numeric', 'min:0'],
        ];
    }

     public function messages(): array
    {
        return [
            'titre.required' => 'Le titre de la mission est obligatoire.',
            'date_depart.after_or_equal' => 'La date de départ ne peut pas être antérieure à aujourd\'hui.',
            'date_retour.after_or_equal' => 'La date de retour doit être postérieure ou égale à la date de départ.',
            'validation_cp_id.exists' => 'Le Chef de Projet sélectionné est invalide.',
            'budget_estime.min' => 'Le budget estimé doit être un nombre positif.',
        ];
    }
}
