ALTER TABLE project_user
    MODIFY rol_asesor ENUM('primario','secundario','asesor','revisor_1','revisor_2') NULL DEFAULT NULL;
