# Credenciales de Prueba SGPI

Todos los usuarios demo usan la misma contraseña:

`Prueba2026!`

## Administrador

| ID | Contraseña | Nombre | Uso principal |
|---|---|---|---|
| 0000000001 | Prueba2026! | Administrador Sistema Principal | Configurar materias, cargas, ventanas y docentes responsables |

## Flujo para probar responsables de propuestas

1. Inicia sesion como administrador `0000000001`.
2. Entra a **Propuestas** en el menu lateral.
3. En **Materia a supervisar**, selecciona `Fundamentos de Ingenieria de Software`.
4. Veras los grupos `5to A - Propuestas 2026` y `5to B - Propuestas 2026` porque esa materia esta ligada a esas cargas.
5. En cada grupo puedes seleccionar un docente y asignarlo como responsable de revisar registros de proyectos.
6. Inicia sesion como uno de esos docentes y entra a **Revisar Propuestas** para aprobar, rechazar o solicitar correcciones.

## Materias demo

| Clave | Materia |
|---|---|
| SWGPI-FIS | Fundamentos de Ingenieria de Software |
| SWGPI-BD | Base de Datos |
| SWGPI-WEB | Programacion Web |
| SWGPI-GP | Gestion de Proyectos de Software |

## Cargas / grupos demo

| Grupo | Semestre | Materias ligadas | Ventana de registro |
|---|---:|---|---|
| 5to A - Propuestas 2026 | 5 | Fundamentos, Base de Datos, Programacion Web | Activa por 15 dias desde la carga del seeder |
| 5to B - Propuestas 2026 | 5 | Fundamentos, Gestion de Proyectos | Activa por 15 dias desde la carga del seeder |
| 6to A - Desarrollo 2026 | 6 | Gestion de Proyectos, Programacion Web | Sin ventana demo |

## Docentes responsables demo para propuestas

| Materia | Grupo | Docente responsable | Contraseña |
|---|---|---|---|
| Fundamentos de Ingenieria de Software | 5to A - Propuestas 2026 | D260001 - Alejandro Ramos Lopez | Prueba2026! |
| Fundamentos de Ingenieria de Software | 5to A - Propuestas 2026 | D260002 - Beatriz Mendez Soto | Prueba2026! |
| Fundamentos de Ingenieria de Software | 5to B - Propuestas 2026 | D260003 - Carlos Herrera Diaz | Prueba2026! |

## Docentes

| ID | Contraseña | Nombre |
|---|---|---|
| D260001 | Prueba2026! | Alejandro Ramos Lopez |
| D260002 | Prueba2026! | Beatriz Mendez Soto |
| D260003 | Prueba2026! | Carlos Herrera Diaz |
| D260004 | Prueba2026! | Daniela Cruz Vega |
| D260005 | Prueba2026! | Eduardo Salinas Mora |
| D260006 | Prueba2026! | Fernanda Castillo Reyes |
| D260007 | Prueba2026! | Gabriel Ortega Nava |
| D260008 | Prueba2026! | Helena Paredes Rios |
| D260009 | Prueba2026! | Ivan Campos Silva |
| D260010 | Prueba2026! | Julia Navarro Leon |

## Estudiantes

Todos tienen perfil inicial completado, semestre 5 y grupo A/B para poder probar el flujo.

| ID | Contraseña | Nombre | Grupo |
|---|---|---|---|
| S260001 | Prueba2026! | Ana Garcia Perez | 5to A |
| S260002 | Prueba2026! | Bruno Martinez Ruiz | 5to A |
| S260003 | Prueba2026! | Camila Lopez Torres | 5to B |
| S260004 | Prueba2026! | Diego Hernandez Flores | 5to B |
| S260005 | Prueba2026! | Elena Sanchez Morales | 5to A |
| S260006 | Prueba2026! | Fabian Ramirez Cortes | 5to A |
| S260007 | Prueba2026! | Grecia Vargas Medina | 5to B |
| S260008 | Prueba2026! | Hugo Jimenez Aguilar | 5to B |
| S260009 | Prueba2026! | Irene Romero Ponce | 5to A |
| S260010 | Prueba2026! | Jorge Fuentes Luna | 5to A |

## Proyectos Demo

| Proyecto | Grupo | Estudiantes | Asesor primario | Asesor secundario | Estado propuesta |
|---|---|---|---|---|---|
| Proyecto Demo 01 - Control de Inventario | 5to A - Propuestas 2026 | S260001, S260002 | D260001 | D260002 | Pendiente |
| Proyecto Demo 02 - Seguimiento Academico | 5to B - Propuestas 2026 | S260003, S260004 | D260003 | D260004 | Pendiente |
| Proyecto Demo 03 - Gestion de Talleres | 6to A - Desarrollo 2026 | S260005, S260006 | D260005 | D260006 | Pendiente |