ALTER TABLE projects
    ADD COLUMN is_thesis TINYINT(1) NOT NULL DEFAULT 0 AFTER activo,
    ADD INDEX projects_is_thesis_index (is_thesis);

ALTER TABLE project_user
    MODIFY rol_asesor ENUM('primario','secundario','asesor','revisor_1','revisor_2') NULL DEFAULT NULL;

UPDATE project_user SET rol_asesor = 'primario' WHERE rol_asesor = 'asesor';
UPDATE project_user SET rol_asesor = 'secundario' WHERE rol_asesor = 'revisor_1';
UPDATE project_user SET rol_asesor = NULL WHERE rol_asesor = 'revisor_2';
