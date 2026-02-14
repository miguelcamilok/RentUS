<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class MailTestController extends Controller
{
    /**
     * 🧪 Test completo de envío de correo con debug detallado
     */
    public function testMailSend(Request $request)
    {
        $recipient = $request->input('email', env('MAIL_USERNAME'));

        Log::info('🧪 Iniciando test de correo', [
            'recipient' => $recipient,
            'timestamp' => now(),
        ]);

        try {
            // Verificar configuración
            $config = [
                'default' => config('mail.default'),
                'driver' => config('mail.mailers.smtp.transport'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'password' => config('mail.mailers.smtp.password') ? 'SET (***' . substr(config('mail.mailers.smtp.password'), -4) . ')' : 'NOT SET',
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ];

            Log::info('📧 Configuración de mail', $config);

            // Verificar que todas las variables requeridas estén presentes
            $missing = [];
            if (!config('mail.mailers.smtp.host')) $missing[] = 'MAIL_HOST';
            if (!config('mail.mailers.smtp.port')) $missing[] = 'MAIL_PORT';
            if (!config('mail.mailers.smtp.username')) $missing[] = 'MAIL_USERNAME';
            if (!config('mail.mailers.smtp.password')) $missing[] = 'MAIL_PASSWORD';
            if (!config('mail.from.address')) $missing[] = 'MAIL_FROM_ADDRESS';

            if (!empty($missing)) {
                Log::error('❌ Faltan variables de configuración', ['missing' => $missing]);
                return response()->json([
                    'success' => false,
                    'error' => 'Configuración incompleta',
                    'missing' => $missing,
                    'config' => $config,
                ], 500);
            }

            // Test 1: Envío simple con Mail::raw
            Log::info('📤 Test 1: Enviando con Mail::raw()');

            Mail::raw('Este es un correo de prueba desde Railway. Timestamp: ' . now(), function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject('Test Mail - ' . now())
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('✅ Mail::raw() ejecutado sin excepciones');

            // Verificar si hay errores en la cola de Swift Mailer
            $failures = [];
            Mail::failures();

            if (!empty($failures)) {
                Log::error('❌ Fallos detectados en Mail::failures()', ['failures' => $failures]);
                return response()->json([
                    'success' => false,
                    'error' => 'Fallos al enviar',
                    'failures' => $failures,
                    'config' => $config,
                ], 500);
            }

            Log::info('✅ Test completado exitosamente');

            return response()->json([
                'success' => true,
                'message' => 'Correo enviado exitosamente a ' . $recipient,
                'config' => $config,
                'timestamp' => now(),
            ]);

        } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
            Log::error('❌ Error de transporte SMTP', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error de transporte SMTP',
                'message' => $e->getMessage(),
                'hint' => 'Verifica que Gmail permita apps menos seguras o usa una App Password',
            ], 500);

        } catch (\Exception $e) {
            Log::error('❌ Error general al enviar correo', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al enviar correo',
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ], 500);
        }
    }

    /**
     * 🔍 Verificar conexión SMTP sin enviar correo
     */
    public function testSmtpConnection()
    {
        try {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            $timeout = 5;

            Log::info('🔌 Testing SMTP connection', [
                'host' => $host,
                'port' => $port,
            ]);

            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if ($socket) {
                fclose($socket);
                Log::info('✅ SMTP connection successful');

                return response()->json([
                    'success' => true,
                    'message' => 'Conexión SMTP exitosa',
                    'host' => $host,
                    'port' => $port,
                ]);
            } else {
                Log::error('❌ SMTP connection failed', [
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo conectar al servidor SMTP',
                    'errno' => $errno,
                    'errstr' => $errstr,
                    'host' => $host,
                    'port' => $port,
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('❌ Error testing SMTP connection', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
