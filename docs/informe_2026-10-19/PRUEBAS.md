# Evidencia de verificación y catálogo de pruebas

Ejecución local del 19 de octubre de 2026, PHP 8.5.11, PHPUnit 11.5.56.

| Comprobación | Resultado |
|---|---|
| `php artisan route:list --json` | 229 registros; 223 API |
| `vendor/bin/phpunit --colors=never` | 48 pruebas, 245 aserciones; aprobadas |
| `./scripts/test-academic-mysql.sh --colors=never` | 37 pruebas, 197 aserciones; aprobadas |
| Suma de ambas suites | 85 pruebas, 442 aserciones |

La primera suite usa SQLite en memoria y transportes de prueba. La segunda crea su propio servidor MySQL temporal con `--skip-networking`, fixture sintético y socket aislado; después lo elimina. No demuestra la configuración ni los datos productivos. No se ejecutaron respaldos reales, envíos de correo, cambios académicos o migraciones contra la conexión de `.env`.

La suite Integration no está incluida en las carpetas Unit/Feature de `phpunit.xml`; necesita invocación separada. El workflow de CI llama `php artisan test` y no incluye el script MySQL.

## tests/Feature/ApiAuthenticationTest.php

Fuente: [ApiAuthenticationTest.php](../../tests/Feature/ApiAuthenticationTest.php).

- `test_protected_api_routes_return_json_unauthorized_response`
## tests/Feature/CorsHeadersTest.php

Fuente: [CorsHeadersTest.php](../../tests/Feature/CorsHeadersTest.php).

- `test_production_frontend_can_complete_api_preflight`
- `test_local_network_php_frontend_can_access_api`
- `test_capacitor_app_can_complete_api_preflight`
- `test_unauthorized_api_response_keeps_cors_headers`
- `test_jwt_exception_becomes_cors_enabled_unauthorized_response`
- `test_internal_api_error_keeps_cors_headers`
- `test_untrusted_origin_never_receives_credentialed_cors_headers`
- `test_preflight_does_not_reflect_unapproved_headers`
## tests/Feature/DeliverySubmissionServiceTest.php

Fuente: [DeliverySubmissionServiceTest.php](../../tests/Feature/DeliverySubmissionServiceTest.php).

- `test_first_submission_creates_delivery_document_and_version_one`
- `test_resubmission_reuses_logical_delivery_and_preserves_version_one`
- `test_repeated_submissions_keep_unique_version_numbers_and_one_delivery`
- `test_a_version_unique_collision_is_retried_in_a_controlled_way`
- `test_wrong_project_is_rejected`
- `test_upload_endpoint_requires_project_id`
- `test_unauthorized_user_is_rejected`
- `test_database_failure_removes_new_file_and_temporary_file`
- `test_legacy_public_version_is_resolved_and_downloaded_by_laravel`
- `test_legacy_fallback_resolves_a_file_missing_from_recorded_disk`
- `test_private_download_can_return_x_accel_header_when_enabled`
- `test_student_listing_uses_latest_version_and_delivery_grade`
- `test_same_deliverable_keeps_projects_and_files_separate`
- `test_teacher_matrix_reads_multiple_project_deliveries_without_duplication`
- `test_grading_updates_only_the_requested_project_delivery`
- `test_repository_replacement_creates_a_new_version_and_keeps_the_previous_file`
- `test_repository_download_visibility_rules_use_latest_version`
- `test_repository_validation_and_database_compensation_use_secure_file_service`
## tests/Feature/ExampleTest.php

Fuente: [ExampleTest.php](../../tests/Feature/ExampleTest.php).

- `test_the_application_returns_a_successful_response`
## tests/Feature/SecurityHardeningTest.php

Fuente: [SecurityHardeningTest.php](../../tests/Feature/SecurityHardeningTest.php).

- `test_api_responses_include_defensive_headers`
- `test_authentication_responses_are_not_cacheable`
- `test_login_rate_limiter_blocks_the_sixth_attempt`
- `test_general_administration_routes_keep_required_guards`
- `test_private_backups_are_outside_the_public_directory`
- `test_user_mutations_require_general_administrator_governance`
## tests/Feature/UserActivationStateTest.php

Fuente: [UserActivationStateTest.php](../../tests/Feature/UserActivationStateTest.php).

- `test_inactive_users_remain_visible_and_can_be_reactivated`
- `test_deactivation_updates_both_effective_states_and_keeps_user_recoverable`
## tests/Integration/AcademicWorkflowsMySqlTest.php

Fuente: [AcademicWorkflowsMySqlTest.php](../../tests/Integration/AcademicWorkflowsMySqlTest.php).

- `test_diagnostics_recognizes_migrated_schema_without_mutating_data`
- `test_mixed_course_team_records_only_enrolled_participants_and_protects_files`
- `test_cross_career_authorization_is_rejected_and_global_profile_does_not_control_teacher_eligibility`
- `test_profile_phone_update_preserves_enrollment_and_rejects_semester_change`
- `test_enrollment_is_idempotent_and_inactive_group_or_expired_window_rejected`
- `test_summary_is_read_only_and_promotion_retries_do_not_duplicate_movements`
- `test_stale_promotion_is_rejected_without_partial_writes`
- `test_authorization_has_explicit_scope_version_and_revocation`
- `test_agenda_rejects_shared_room_across_careers_and_accepts_adjacent_slots`
- `test_room_wrong_stage_needs_authorization`
- `test_membership_cannot_be_demoted_with_pending_courses`
- `test_residency_cannot_close_without_reviewed_documentation_and_is_individual`
- `test_closing_self_registration_preserves_course_and_enrolled_student_access`
- `test_individual_enrollment_controls_deliverables_and_baja`
- `test_residency_upload_review_close_and_private_download`
- `test_expired_and_cross_career_decisions_preserve_authorization_history`
- `test_closure_blocks_pending_room_without_affecting_other_career`
- `test_catalog_and_room_creation_work_on_normalized_schema`
- `test_preparation_job_never_activates_or_promotes`
- `test_simultaneous_reservations_cannot_book_same_resource`
- `test_reconciliation_requires_explicit_legacy_room_resolution_and_preserves_evaluations`
- `test_exit_decision_cannot_repeat_under_a_different_operation_id`
- `test_failed_second_movement_rolls_back_entire_promotion_batch`
- `test_retry_keeps_prior_results_and_links_exact_approved_source`
- `test_stage_approval_is_bound_to_created_evaluation_and_cannot_be_revoked_after_use`
- `test_settings_and_document_queries_do_not_create_activities_or_infer_period_from_name`
- `test_real_role_and_period_middleware_prevent_student_governance_and_closed_writes`
- `test_teaching_assignments_use_membership_and_normalized_courses_and_keep_audit_history`
- `test_proposal_status_uses_current_enrolled_course_and_does_not_depend_on_legacy_window_tables`
- `test_group_change_cannot_rewrite_academic_history`
- `test_old_results_keep_teacher_identity_after_membership_deactivation`
- `test_ninth_semester_stays_in_residency_and_new_company_project_can_keep_old_integrator_link`
- `test_maintenance_expires_authorizations_and_notifies_once_without_promoting`
- `test_room_edit_cannot_relabel_existing_evaluation_stage_or_period`
- `test_room_without_active_criteria_is_rejected_atomically_and_can_be_retried`
- `test_real_scoring_persists_normalized_response_and_preserves_criteria_and_results`
## tests/TestCase.php

Fuente: [TestCase.php](../../tests/TestCase.php).

## tests/Unit/AcademicPeriodTest.php

Fuente: [AcademicPeriodTest.php](../../tests/Unit/AcademicPeriodTest.php).

- `test_first_half_contains_sixth_and_eighth_semesters`
- `test_second_half_contains_fifth_seventh_and_ninth_semesters`
## tests/Unit/CareerProfilesAuthorizationTest.php

Fuente: [CareerProfilesAuthorizationTest.php](../../tests/Unit/CareerProfilesAuthorizationTest.php).

- `test_career_head_inherits_career_administration`
- `test_only_general_administrator_governs_user_accounts`
- `test_assistant_cannot_use_critical_administration`
- `test_project_coordinator_is_limited_to_project_management`
## tests/Unit/EnsureUserIsActiveTest.php

Fuente: [EnsureUserIsActiveTest.php](../../tests/Unit/EnsureUserIsActiveTest.php).

- `test_cache_failure_does_not_break_authenticated_request`
## tests/Unit/ExampleTest.php

Fuente: [ExampleTest.php](../../tests/Unit/ExampleTest.php).

- `test_that_true_is_true`
## tests/Unit/StoredFileServiceTest.php

Fuente: [StoredFileServiceTest.php](../../tests/Unit/StoredFileServiceTest.php).

- `test_prepares_promotes_and_hashes_a_valid_pdf`
- `test_rejects_php_and_dangerous_double_extensions`
- `test_rejects_mime_mismatch_and_compressed_file_renamed_as_pdf`
- `test_accepts_valid_zip_rar_and_7z_signatures`
