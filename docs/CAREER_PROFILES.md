# Perfiles de gestión por carrera

Los perfiles se asignan mediante `usuario_carrera.perfil_id`. No existe una
dependencia obligatoria entre Administrador y Jefe de Carrera: ambos pueden
coexistir, o una carrera puede operar con cualquiera de los dos.

| ID | Perfil | Alcance |
|---:|---|---|
| 1 | Administrador | Administración operativa de la carrera, sin gobierno de cuentas |
| 2 | Docente | Docencia, asesoría y evaluación asignada |
| 3 | Estudiante | Proyectos y entregables propios |
| 4 | Administrador General | Administración institucional multicarrera |
| 5 | Jefe de Carrera | Administración operativa de su carrera, sin gobierno de cuentas |
| 6 | Asistente de Jefe de Carrera | Operación académica y gestión de proyectos, sin controles críticos |
| 7 | Coordinador de Proyectos | Proyectos, propuestas, entregables y evaluaciones |

## Capacidades

| Capacidad | Admin. general | Administrador | Jefe | Asistente | Coordinador | Docente | Estudiante |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Administración multicarrera | Sí | No | No | No | No | No | No |
| Crear, editar, desactivar o eliminar cuentas y perfiles | Sí | No | No | No | No | No | No |
| Configuración operativa de la carrera | Sí | Sí | Sí | No | No | No | No |
| Periodos, grupos y asignaturas | Sí | Sí | Sí | Sí | No | Consulta | Consulta |
| Proyectos, propuestas y asesores | Sí | Sí | Sí | Sí | Sí | Asignados | Propios |
| Entregables y evaluaciones | Sí | Sí | Sí | Sí | Sí | Asignados | Propios |

El script idempotente para registrar los perfiles es
`database/sql/2026_08_18_add_career_management_profiles.sql`.

Los cargos operativos pueden consultar únicamente los datos mínimos de una
persona necesarios para asignaciones académicas (`id`, nombre, perfil y
estado). El expediente completo y todas las mutaciones de cuenta usan la
capacidad `user_governance`, concedida exclusivamente al perfil 4.
