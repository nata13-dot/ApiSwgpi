<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users' => 'usuarios',
        'projects' => 'proyectos',
        'project_user' => 'proyectos_integrantes',
        'project_asignatura' => 'proyectos_asignaturas',
        'avances' => 'avances_proyecto',
        'deliverables' => 'entregables_proyecto',
        'document_tags' => 'etiquetas_documentos',
        'deliverable_document_tag' => 'entregables_etiquetas',
        'document_versions' => 'versiones_documentos',
        'repository_documents' => 'documentos_repositorio',
        'repository_document_tag' => 'documentos_repositorio_etiquetas',
        'subject_groups' => 'grupos_academicos',
        'subject_group_asignatura' => 'grupos_asignaturas',
        'teacher_group_assignments' => 'asignaciones_docentes_grupos',
        'project_registration_windows' => 'ventanas_registro_proyectos',
        'evaluation_rooms' => 'salas_evaluacion',
        'evaluation_room_project' => 'salas_evaluacion_proyectos',
        'evaluation_room_teacher' => 'salas_evaluacion_docentes',
        'evaluations' => 'evaluaciones',
        'evaluation_attempts' => 'intentos_evaluacion',
        'evaluation_scores' => 'respuestas_evaluacion',
        'rubric_criteria' => 'criterios_rubrica',
        'proposal_review_exceptions' => 'excepciones_revision_propuesta',
        'system_settings' => 'configuraciones_sistema',
    ];

    public function up(): void
    {
        foreach ($this->tables as $current => $normalized) {
            if (Schema::hasTable($current) && ! Schema::hasTable($normalized)) {
                Schema::rename($current, $normalized);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables, true) as $current => $normalized) {
            if (Schema::hasTable($normalized) && ! Schema::hasTable($current)) {
                Schema::rename($normalized, $current);
            }
        }
    }
};
