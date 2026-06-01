ALTER TABLE project_user
    MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2','primario','secundario') NULL DEFAULT NULL;

UPDATE project_user SET rol_asesor = 'asesor' WHERE rol_asesor = 'primario';
UPDATE project_user SET rol_asesor = 'revisor_1' WHERE rol_asesor = 'secundario';

ALTER TABLE project_user
    MODIFY rol_asesor ENUM('asesor','revisor_1','revisor_2') NULL DEFAULT NULL;
