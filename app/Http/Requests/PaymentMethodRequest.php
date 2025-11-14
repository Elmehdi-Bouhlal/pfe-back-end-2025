<?php
// app/Http/Requests/PaymentMethodRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'type' => 'required|in:cash_on_delivery,paypal,bank_card',
            'provider_name' => 'nullable|string|max:100',
            'is_default' => 'sometimes|boolean',
            'details' => 'nullable|array',
            
            // PayPal specific
            'details.email' => 'required_if:type,paypal|nullable|email|max:255',
            
            // Bank card specific
            'details.card_number' => 'required_if:type,bank_card|nullable|string|size:16',
            'details.expiry_month' => 'required_if:type,bank_card|nullable|integer|between:1,12',
            'details.expiry_year' => 'required_if:type,bank_card|nullable|integer|min:' . date('Y'),
            'details.cvv' => 'required_if:type,bank_card|nullable|string|size:3',
            'details.cardholder_name' => 'required_if:type,bank_card|nullable|string|max:100'
        ];
    }

    public function messages()
    {
        return [
            'type.required' => 'Le type de méthode de paiement est requis',
            'type.in' => 'Type de méthode de paiement non valide',
            
            'details.email.required_if' => 'L\'email PayPal est requis',
            'details.email.email' => 'Format d\'email PayPal invalide',
            
            'details.card_number.required_if' => 'Le numéro de carte est requis',
            'details.card_number.size' => 'Le numéro de carte doit contenir 16 chiffres',
            'details.expiry_month.required_if' => 'Le mois d\'expiration est requis',
            'details.expiry_month.between' => 'Le mois d\'expiration doit être entre 1 et 12',
            'details.expiry_year.required_if' => 'L\'année d\'expiration est requise',
            'details.expiry_year.min' => 'L\'année d\'expiration ne peut pas être dans le passé',
            'details.cvv.required_if' => 'Le code CVV est requis',
            'details.cvv.size' => 'Le code CVV doit contenir 3 chiffres',
            'details.cardholder_name.required_if' => 'Le nom du titulaire est requis'
        ];
    }

    protected function prepareForValidation()
    {
        // Clean and format card number
        if ($this->type === 'bank_card' && isset($this->details['card_number'])) {
            $cardNumber = preg_replace('/\D/', '', $this->details['card_number']);
            $details = $this->details;
            $details['card_number'] = $cardNumber;
            $this->merge(['details' => $details]);
        }

        // Set provider name based on type
        if (empty($this->provider_name)) {
            $providerNames = [
                'paypal' => 'PayPal',
                'cash_on_delivery' => 'Paiement à la livraison',
                'bank_card' => 'Carte bancaire'
            ];
            
            $this->merge(['provider_name' => $providerNames[$this->type] ?? ucfirst($this->type)]);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional validation for bank cards
            if ($this->type === 'bank_card' && isset($this->details['card_number'])) {
                $cardNumber = $this->details['card_number'];
                
                // Basic Luhn algorithm check
                if (!$this->validateCardNumber($cardNumber)) {
                    $validator->errors()->add('details.card_number', 'Numéro de carte invalide');
                }
            }
        });
    }

    private function validateCardNumber($cardNumber)
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        
        if (strlen($cardNumber) !== 16) {
            return false;
        }

        // Simple Luhn algorithm
        $sum = 0;
        $reverse = strrev($cardNumber);
        
        for ($i = 0; $i < strlen($reverse); $i++) {
            $digit = intval($reverse[$i]);
            
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }
            
            $sum += $digit;
        }

        return ($sum % 10) === 0;
    }
}