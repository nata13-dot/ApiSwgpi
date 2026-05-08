<?php

namespace App\Services;

use App\Models\Competencia;
use App\Models\Project;
use App\Models\Deliverable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Validador centralizado de reglas de negocio
 * 
 * Contiene todas las validaciones académicas del sistema SWGPI
 */
class BusinessValidationService
{
    /**
     * Validar que los asesores asignados sean únicos por proyecto
     * 
     * Un proyecto no puede tener dos asesores iguales
     * Solo puede haber 1 asesor primario y 1 asesor secundario
     * 
     * @param int $project_id ID del proyecto
     * @param string|null $excluded_advisor ID del asesor a excluir (para updates)
     * @return bool true si es válido, false si hay duplicados
     */
    public static function validateAsesoresUnicos(int $project_id, ?string $excluded_advisor = null): bool
    {
        $query = Project::find($project_id)
            ->advisors()
            ->whereNotNull('rol_asesor');
        
        if ($excluded_advisor) {
            $query->where('user_id', '!=', $excluded_advisor);
        }
        
        $advisors = $query->get();
        
        // Verificar que no haya duplicados en el rol
        $primarios = $advisors->where('pivot.rol_asesor', 'primario')->count();
        $secundarios = $advisors->where('pivot.rol_asesor', 'secundario')->count();
        
        // Solo un asesor por tipo
        return $primarios <= 1 && $secundarios <= 1;
    }
    
    /**
     * Validar que un entregable esté dentro del rango de fechas de su competencia
     * 
     * @param int $competencia_id ID de la competencia
     * @param string $fecha_entregable Fecha del entregable (YYYY-MM-DD)
     * @return bool true si está dentro del rango
     */
    public static function validateFechaEntregableEnCompetencia(
        int $competencia_id,
        string $fecha_entregable
    ): bool
    {
        $competencia = Competencia::find($competencia_id);
        
        if (!$competencia || !$competencia->fecha_inicio || !$competencia->fecha_fin) {
            return true; // Si competencia no tiene fechas, no validar
        }
        
        $fecha = \strtotime($fecha_entregable);
        $inicio = \strtotime($competencia->fecha_inicio);
        $fin = \strtotime($competencia->fecha_fin);
        
        return $fecha >= $inicio && $fecha <= $fin;
    }
    
    /**
     * Validar que el usuario tiene acceso a un entregable
     * 
     * - Admin: acceso a todos
     * - Docente: solo a sus proyectos (donde es asesor)
     * - Estudiante: solo a los suyos (donde es miembro del proyecto)
     * 
     * @param int $deliverable_id ID del entregable
     * @param string $user_id ID del usuario (perfil desde token)
     * @param int $perfil_id 1=Admin, 2=Docente, 3=Estudiante
     * @return bool true si tiene acceso
     */
    public static function validateAccesoEntrega(
        int $deliverable_id,
        string $user_id,
        int $perfil_id
    ): bool
    {
        // Admin: acceso total
        if ($perfil_id === 1) {
            return true;
        }
        
        $deliverable = Deliverable::find($deliverable_id);
        if (!$deliverable) {
            return false;
        }
        
        $project = $deliverable->project;
        
        // Docente: solo sus proyectos (donde es asesor)
        if ($perfil_id === 2) {
            return $project->advisors()
                ->where('user_id', $user_id)
                ->where('rol_asesor', '!=', null)
                ->exists();
        }
        
        // Estudiante: solo proyectos donde es miembro
        if ($perfil_id === 3) {
            return $project->students()
                ->where('user_id', $user_id)
                ->exists();
        }
        
        return false;
    }
    
    /**
     * Validar que una competencia tiene fechas de rango válidas
     * 
     * @param \Carbon\Carbon|string $fecha_inicio Fecha inicio
     * @param \Carbon\Carbon|string $fecha_fin Fecha fin
     * @return bool true si inicio < fin
     */
    public static function validateRangoFechas($fecha_inicio, $fecha_fin): bool
    {
        $inicio = \strtotime((string)$fecha_inicio);
        $fin = \strtotime((string)$fecha_fin);
        
        return $inicio < $fin;
    }
    
    /**
     * Validar que una calificación está en rango válido (0-100)
     * 
     * @param mixed $calificacion Valor a validar
     * @return bool true si está entre 0 y 100
     */
    public static function validateCalificacion($calificacion): bool
    {
        $valor = (float)$calificacion;
        return $valor >= 0 && $valor <= 100;
    }
    
    /**
     * Obtener todos los errores de validación de un entregable
     * 
     * @param array $data Datos del entregable
     * @return array Array de errores (vacío si no hay errores)
     */
    public static function validarEntregableCompleto(array $data): array
    {
        $errores = [];
        
        // Validar competencia
        if (!empty($data['competencia_id'])) {
            if (!self::validateFechaEntregableEnCompetencia(
                $data['competencia_id'],
                $data['fecha_limite'] ?? date('Y-m-d')
            )) {
                $errores[] = 'La fecha del entregable está fuera del rango de la competencia';
            }
        }
        
        // Validar calificación si existe
        if (!empty($data['calificacion']) && !self::validateCalificacion($data['calificacion'])) {
            $errores[] = 'La calificación debe estar entre 0 y 100';
        }
        
        return $errores;
    }
    
    /**
     * Obtener todos los errores de validación de un proyecto
     * 
     * @param int $project_id ID del proyecto
     * @param array $data Datos a validar
     * @return array Array de errores
     */
    public static function validarProyectoCompleto(int $project_id, array $data): array
    {
        $errores = [];
        
        // Validar asesores únicos
        if (!self::validateAsesoresUnicos($project_id)) {
            $errores[] = 'No se puede asignar asesores duplicados al proyecto';
        }
        
        // Validar año válido
        if (!empty($data['year'])) {
            $anio = (int)$data['year'];
            $anio_actual = date('Y');
            
            if ($anio < $anio_actual - 5 || $anio > $anio_actual + 5) {
                $errores[] = "El año debe estar entre " . ($anio_actual - 5) . " y " . ($anio_actual + 5);
            }
        }
        
        return $errores;
    }
}
