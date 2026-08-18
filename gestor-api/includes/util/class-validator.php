<?php
/**
 * Validacao e sanitizacao de inputs da API.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Util;

use Gestor_Api\Util\Ulid;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Excecao de validacao. Carrega WP_Error pronto pra resposta.
 */
final class Gestor_Api_Validation_Exception extends \RuntimeException
{
    public WP_Error $wp_error;

    public function __construct(string $code, string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->wp_error = new WP_Error($code, $message, ['status' => $status]);
    }
}

/**
 * Validador central de payloads.
 */
final class Validator
{
    public const STATUS_TAREFA = [
        'CAIXA_ENTRADA',
        'PLANEJADA',
        'EM_ANDAMENTO',
        'AGUARDANDO_TERCEIRO',
        'BLOQUEADA',
        'EM_REVISAO',
        'ENTREGUE_AGUARDANDO_CONFIRMACAO',
        'CONCLUIDA',
        'ADIADA',
        'CANCELADA',
        'ARQUIVADA',
    ];

    public const STATUS_PROJETO = [
        'PLANEJADO',
        'EM_ANDAMENTO',
        'PAUSADO',
        'CONCLUIDO',
        'CANCELADO',
        'ARQUIVADO',
    ];

    public const STATUS_CLIENTE = ['ATIVO', 'INATIVO', 'ARQUIVADO'];

    public const PRIORIDADE = ['BAIXA', 'NORMAL', 'ALTA', 'URGENTE', 'CRITICA'];

    public const NIVEL_COBRANCA = ['DISCRETA', 'PERSISTENTE', 'INTENSIVA', 'CRITICA'];

    public const TOM_COBRANCA = ['PROFISSIONAL', 'FIRME', 'GENTIL'];

    public const ORIGEM = ['MANUAL', 'NL', 'IMPORTADA', 'EMAIL', 'OUTRO'];

    /**
     * Lanca excecao se valor for vazio/nulo.
     *
     * @param mixed  $value Valor a checar.
     * @param string $field Nome do campo.
     * @param string $code Codigo do erro.
     */
    public static function require_field($value, string $field, string $code = 'campo_obrigatorio'): void
    {
        if ($value === null || $value === '' || $value === []) {
            throw new Gestor_Api_Validation_Exception(
                $code,
                sprintf('Campo obrigatorio ausente: %s', $field)
            );
        }
    }

    /**
     * Valida e retorna string.
     */
    public static function string($value, string $field, int $max_len = 255): string
    {
        if ($value === null) {
            return '';
        }
        $clean = sanitize_text_field((string) $value);
        if (strlen($clean) > $max_len) {
            throw new Gestor_Api_Validation_Exception(
                'campo_muito_longo',
                sprintf('Campo %s excede %d caracteres', $field, $max_len)
            );
        }
        return $clean;
    }

    /**
     * Valida email.
     */
    public static function email($value, string $field = 'email'): string
    {
        $clean = sanitize_email((string) $value);
        if ($clean === '' || !is_email($clean)) {
            throw new Gestor_Api_Validation_Exception(
                'email_invalido',
                'Email invalido'
            );
        }
        return strtolower($clean);
    }

    /**
     * Valida senha forte.
     */
    public static function senha($value, string $field = 'senha'): string
    {
        $s = (string) $value;
        if (strlen($s) < 8) {
            throw new Gestor_Api_Validation_Exception(
                'senha_curta',
                'Senha deve ter ao menos 8 caracteres'
            );
        }
        if (!preg_match('/[A-Z]/', $s)) {
            throw new Gestor_Api_Validation_Exception(
                'senha_sem_maiuscula',
                'Senha deve ter ao menos 1 maiuscula'
            );
        }
        if (!preg_match('/[a-z]/', $s)) {
            throw new Gestor_Api_Validation_Exception(
                'senha_sem_minuscula',
                'Senha deve ter ao menos 1 minuscula'
            );
        }
        if (!preg_match('/[0-9]/', $s)) {
            throw new Gestor_Api_Validation_Exception(
                'senha_sem_numero',
                'Senha deve ter ao menos 1 numero'
            );
        }
        return $s;
    }

    /**
     * Valida enums.
     *
     * @param array<int, string> $allowed
     */
    public static function enum($value, string $field, array $allowed, ?string $default = null): string
    {
        if ($value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }
            throw new Gestor_Api_Validation_Exception(
                'campo_obrigatorio',
                sprintf('Campo %s e obrigatorio', $field)
            );
        }
        $s = (string) $value;
        if (!in_array($s, $allowed, true)) {
            throw new Gestor_Api_Validation_Exception(
                'valor_invalido',
                sprintf('Campo %s invalido. Valores permitidos: %s', $field, implode(', ', $allowed))
            );
        }
        return $s;
    }

    /**
     * Valida ULID ou gera um novo se vazio.
     */
    public static function ulid($value, string $field, bool $auto_generate = true): string
    {
        if ($value === null || $value === '') {
            if ($auto_generate) {
                return Ulid::generate();
            }
            throw new Gestor_Api_Validation_Exception(
                'ulid_obrigatorio',
                sprintf('Campo %s deve ser ULID', $field)
            );
        }
        $s = (string) $value;
        if (!Ulid::is_valid($s)) {
            throw new Gestor_Api_Validation_Exception(
                'ulid_invalido',
                sprintf('Campo %s nao e ULID valido', $field)
            );
        }
        return $s;
    }

    /**
     * Valida ULID opcional (retorna null se vazio).
     */
    public static function ulid_optional($value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = (string) $value;
        if (!Ulid::is_valid($s)) {
            throw new Gestor_Api_Validation_Exception(
                'ulid_invalido',
                sprintf('Campo %s nao e ULID valido', $field)
            );
        }
        return $s;
    }

    /**
     * Valida timestamp ISO 8601.
     */
    public static function iso8601($value, string $field, bool $required = false): ?string
    {
        if ($value === null || $value === '') {
            if ($required) {
                throw new Gestor_Api_Validation_Exception(
                    'campo_obrigatorio',
                    sprintf('Campo %s e obrigatorio', $field)
                );
            }
            return null;
        }
        $s = (string) $value;
        $ts = strtotime($s);
        if ($ts === false) {
            throw new Gestor_Api_Validation_Exception(
                'data_invalida',
                sprintf('Campo %s nao e data ISO 8601 valida', $field)
            );
        }
        return gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }

    /**
     * Valida inteiro positivo opcional.
     */
    public static function int_optional($value, string $field, int $min = 0, ?int $max = null): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $i = (int) $value;
        if ($i < $min) {
            throw new Gestor_Api_Validation_Exception(
                'valor_invalido',
                sprintf('Campo %s deve ser >= %d', $field, $min)
            );
        }
        if ($max !== null && $i > $max) {
            throw new Gestor_Api_Validation_Exception(
                'valor_invalido',
                sprintf('Campo %s deve ser <= %d', $field, $max)
            );
        }
        return $i;
    }

    /**
     * Valida decimal (0-1).
     */
    public static function decimal_0_1($value, string $field): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $f = (float) $value;
        if ($f < 0.0 || $f > 1.0) {
            throw new Gestor_Api_Validation_Exception(
                'valor_invalido',
                sprintf('Campo %s deve estar entre 0 e 1', $field)
            );
        }
        return round($f, 2);
    }

    /**
     * Valida JSON.
     */
    public static function json($value, string $field, bool $required = false): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            if ($required) {
                return [];
            }
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                throw new Gestor_Api_Validation_Exception(
                    'json_invalido',
                    sprintf('Campo %s nao e JSON valido', $field)
                );
            }
            return $decoded;
        }
        throw new Gestor_Api_Validation_Exception(
            'json_invalido',
            sprintf('Campo %s nao e JSON valido', $field)
        );
    }

    /**
     * Sanitiza string permitindo HTML basico.
     */
    public static function rich_text($value, string $field): string
    {
        return wp_kses_post((string) ($value ?? ''));
    }

    /**
     * Sanitiza texto simples.
     */
    public static function text($value, string $field): string
    {
        return sanitize_text_field((string) ($value ?? ''));
    }
}
