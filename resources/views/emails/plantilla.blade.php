{{-- resources/views/emails/plantilla.blade.php --}}
@component('mail::message')
<style>
    .header {
        background-color: #213362;
        padding: 25px;
        text-align: center;
        border-bottom: 3px solid #B7A053;
    }
    .header img {
        max-height: 70px;
    }
    .content {
        padding: 30px;
        font-family: 'Arial', sans-serif;
        line-height: 1.6;
        color: #333333;
        background-color: #ffffff;
    }
    .footer {
        background-color: #213362;
        padding: 20px;
        text-align: center;
        font-size: 12px;
        color: #EBDDB7;
        border-top: 1px solid #B7A053;
    }
    .button-primary {
        background-color: #B7A053;
        color: #ffffff !important;
        padding: 12px 24px;
        text-decoration: none;
        border-radius: 4px;
        display: inline-block;
        margin: 20px 0;
        font-weight: bold;
        border: 1px solid #B7A053;
    }
    .button-primary:hover {
        background-color: #9c8a46;
    }
    .message-content {
        background-color: #f9f9f9;
        padding: 20px;
        border-left: 4px solid #B7A053;
        margin: 20px 0;
        border-radius: 0 4px 4px 0;
    }
    .signature {
        color: #213362;
        margin-top: 30px;
        font-style: italic;
    }
    a {
        color: #B7A053;
    }
    a:hover {
        color: #213362;
    }
</style>

<div class="header">
    <!-- Imagen removida -->
</div>

<div class="content">
    <h1 style="color: #213362; font-size: 22px; margin-bottom: 10px; border-bottom: 2px solid #EBDDB7; padding-bottom: 10px;">
        {{ $data['asunto'] }}
    </h1>
    
    <p style="color: #213362; font-weight: bold;">Estimado/a {!! nl2br(e($data['nombre'] ?? '')) !!},</p>
    
    <div class="message-content">
        {!! nl2br(e($data['mensaje'])) !!}
    </div>

    @component('mail::button', ['url' => 'https://american-edu.com/', 'color' => 'primary'])
        Visite nuestro sitio web
    @endcomponent

    <div class="signature">
        <p style="margin: 5px 0;">Atentamente,</p>
        <p style="margin: 5px 0; font-weight: bold;">Equipo ASM</p>
        <p style="margin: 5px 0;">American School of Management</p>
    </div>
</div>

<div class="footer">
    © {{ date('Y') }} American School of Management. Todos los derechos reservados.<br>
    <div style="margin-top: 10px;">
        <a href="https://american-edu.com/privacy" style="color: #EBDDB7; text-decoration: none; margin: 0 10px;">Política de Privacidad</a> | 
        <a href="https://american-edu.com/terms" style="color: #EBDDB7; text-decoration: none; margin: 0 10px;">Términos de Servicio</a> | 
        <a href="https://american-edu.com/contact" style="color: #EBDDB7; text-decoration: none; margin: 0 10px;">Contacto</a>
    </div>
</div>
@endcomponentss