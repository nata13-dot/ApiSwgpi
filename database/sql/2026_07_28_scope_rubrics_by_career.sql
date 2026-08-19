ALTER TABLE rubricas
    DROP INDEX uq_rubrica_etapa_semestre,
    ADD UNIQUE KEY uq_rubrica_carrera_etapa_semestre (carrera_id, etapa, semestre);
