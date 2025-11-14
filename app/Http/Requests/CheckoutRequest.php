<?php
// app/Http/Requests/CheckoutRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'payment_method' => 'required|in:cash_on_delivery,paypal,bank_card',
            'promo_code' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            
            // Shipping address
            'shipping_address' => 'required|array',
            'shipping_address.first_name' => 'required|string|max:100',
            'shipping_address.last_name' => 'required|string|max:100',
            'shipping_address.email' => 'required|email|max:255',
            'shipping_address.phone' => 'required|string|max:20',
            'shipping_address.address_line_1' => 'required|string|max:255',
            'shipping_address.address_line_2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required|string|max:100',
            'shipping_address.state' => 'nullable|string|max:100',
            'shipping_address.postal_code' => 'nullable|string|max:20',
            'shipping_address.country' => 'required|string|size:2',
            
            // Billing address (optional, defaults to shipping)
            'billing_address' => 'nullable|array',
            'billing_address.first_name' => 'required_with:billing_address|string|max:100',
            'billing_address.last_name' => 'required_with:billing_address|string|max:100',
            'billing_address.email' => 'required_with:billing_address|email|max:255',
            'billing_address.phone' => 'required_with:billing_address|string|max:20',
            'billing_address.address_line_1' => 'required_with:billing_address|string|max:255',
            'billing_address.address_line_2' => 'nullable|string|max:255',
            'billing_address.city' => 'required_with:billing_address|string|max:100',
            'billing_address.state' => 'nullable|string|max:100',
            'billing_address.postal_code' => 'nullable|string|max:20',
            'billing_address.country' => 'required_with:billing_address|string|size:2',
            
            // Payment method specific fields
            'paypal_email' => 'required_if:payment_method,paypal|nullable|email|max:255'
        ];
    }

    public function messages()
    {
        return [
            'payment_method.required' => 'Veuillez sélectionner une méthode de paiement',
            'payment_method.in' => 'Méthode de paiement non valide',
            
            'shipping_address.required' => 'L\'adresse de livraison est requise',
            'shipping_address.first_name.required' => 'Le prénom est requis',
            'shipping_address.last_name.required' => 'Le nom est requis',
            'shipping_address.email.required' => 'L\'email est requis',
            'shipping_address.email.email' => 'Format d\'email invalide',
            'shipping_address.phone.required' => 'Le numéro de téléphone est requis',
            'shipping_address.address_line_1.required' => 'L\'adresse est requise',
            'shipping_address.city.required' => 'La ville est requise',
            'shipping_address.country.required' => 'Le pays est requis',
            
            'paypal_email.required_if' => 'L\'email PayPal est requis pour le paiement PayPal',
            'paypal_email.email' => 'Format d\'email PayPal invalide'
        ];
    }

    protected function prepareForValidation()
    {
        // Set default country to Morocco if not provided
        if ($this->has('shipping_address') && !isset($this->shipping_address['country'])) {
            $shippingAddress = $this->shipping_address;
            $shippingAddress['country'] = 'MA';
            $this->merge(['shipping_address' => $shippingAddress]);
        }
    }
}