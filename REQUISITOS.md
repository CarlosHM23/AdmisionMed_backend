# Matriz de requisitos

Esta matriz propone los requisitos del Sistema de Admisión Médica a partir de
las funcionalidades y rutas disponibles en el backend. Los valores de
MoSCoW son una priorización inicial y pueden ajustarse con los interesados.

| ID | Tipo | Descripción del requisito | Fuente / interesado | MoSCoW | Métrica o forma de verificación | Épica |
|---|---|---|---|---|---|---|
| R-01 | RF | Autenticar a los usuarios mediante credenciales válidas y emitir tokens para acceder a las funciones protegidas. | Operador / Supervisor | Must | Prueba de login correcto, credenciales inválidas y acceso denegado a una ruta protegida. | E1 - Acceso y seguridad |
| R-02 | RF | Permitir consultar y actualizar el perfil del usuario autenticado, incluyendo el cambio de contraseña. | Operador / Supervisor | Should | Las operaciones de consulta, actualización y cambio de contraseña responden correctamente y validan los datos recibidos. | E1 - Acceso y seguridad |
| R-03 | RF | Registrar, consultar, actualizar y eliminar pacientes con sus datos de identificación y contacto. | Personal de admisión | Must | Pruebas CRUD exitosas; los datos guardados se recuperan con el mismo identificador y las validaciones rechazan información inválida. | E2 - Gestión de pacientes |
| R-04 | RF | Generar el siguiente número de historia clínica disponible para un nuevo paciente. | Personal de admisión | Must | La respuesta entrega un número disponible y no duplica un número ya registrado. | E2 - Gestión de pacientes |
| R-05 | RF | Gestionar el catálogo de especialidades médicas mediante operaciones de alta, consulta, modificación y eliminación. | Supervisor | Must | Pruebas CRUD; una especialidad eliminada o actualizada se refleja en las consultas posteriores. | E3 - Catálogos |
| R-06 | RF | Gestionar el personal de salud y asociarlo con la especialidad que corresponda. | Supervisor / Personal de admisión | Must | Pruebas CRUD y validación de la relación entre personal de salud y especialidad. | E3 - Catálogos |
| R-07 | RF | Registrar, consultar, actualizar y eliminar citas asociando paciente, especialidad, personal de salud, fecha, estado y operador. | Personal de admisión | Must | Pruebas CRUD; una cita conserva sus relaciones y no puede registrarse con datos obligatorios inválidos. | E4 - Gestión de citas |
| R-08 | RF | Asignar tickets consecutivos por fecha y especialidad, respetando el límite de cupos configurado. | Personal de admisión / Coordinador de turnos | Must | Crear citas de distintas especialidades produce numeraciones independientes; una solicitud que supera el cupo es rechazada. | E4 - Gestión de citas |
| R-09 | RF | Permitir consultar citas mediante filtros de búsqueda, estado, especialidad, personal de salud y fecha. | Personal de admisión / Supervisor | Should | Cada filtro devuelve únicamente los registros coincidentes y la combinación de filtros reduce el conjunto correctamente. | E4 - Gestión de citas |
| R-10 | RF | Mostrar estadísticas operativas y generar reportes de personal y pacientes atendidos. | Supervisor / Dirección del centro de salud | Should | Los endpoints de dashboard y reportes responden con datos consistentes con las citas y pacientes registrados. | E5 - Reportes y seguimiento |
| R-11 | RNF | Proteger los endpoints privados mediante autenticación y autorización basada en roles, diferenciando al menos los roles operador y supervisor. | Responsable de seguridad / Supervisor | Must | Pruebas de acceso con token válido, token ausente, token expirado y rol no autorizado. | E1 - Acceso y seguridad |
| R-12 | RNF | Mantener la integridad y consistencia de la información relacionada entre pacientes, citas, especialidades y personal de salud. | Administrador de base de datos | Must | Pruebas de claves foráneas, validaciones de entrada y rechazo de referencias inexistentes o duplicadas. | E6 - Calidad de datos |
| R-13 | RNF | Responder las consultas habituales de pacientes, citas y catálogos en un tiempo máximo objetivo de 2 segundos en el servidor local de la admisionista bajo una carga normal definida por el equipo. | Área de TI | Should | Prueba de rendimiento con datos representativos y registro del percentil 95 de respuesta en la red local. | E7 - Rendimiento |
| R-14 | RNF | Exponer las operaciones del sistema mediante una API REST con respuestas JSON consistentes y códigos HTTP apropiados para los clientes frontend de la red local. | Equipo frontend / Equipo backend | Must | Pruebas de contrato verifican estructura de respuesta, códigos 2xx/4xx/5xx y acceso desde un equipo cliente autorizado. | E8 - Integración |
| R-15 | RNF | Permitir que los clientes frontend del supervisor y demás usuarios accedan al sistema desde la red local sin pérdida de información ni controles inaccesibles. | Personal de admisión / Supervisor | Should | Pruebas de acceso desde servidor y equipos cliente en las resoluciones utilizadas por el centro de salud. | E9 - Usabilidad |
| R-16 | RNF | Facilitar el mantenimiento del despliegue local mediante configuración documentada, migraciones y ejecución reproducible del backend, frontend y base de datos en la máquina de la admisionista. | Equipo de desarrollo / Área de TI | Should | Un entorno limpio puede instalar dependencias, ejecutar migraciones y levantar el servidor local siguiendo el manual técnico. | E10 - Operación local |
| R-18 | RNF | Mantener los datos clínicos y administrativos dentro de la máquina servidor de la admisionista y su red local autorizada, sin enviarlos a servicios externos. | Dirección del centro de salud / Responsable de seguridad | Must | Revisión de configuración y prueba de tráfico confirman que la aplicación opera localmente y no realiza transferencias externas de datos. | E10 - Operación local |
| R-17 | RF | Generar comprobante digital de cita en PDF con código QR de confirmación. | Paciente | Must | Generación del archivo PDF descargable (CP-05) | E3 |

## Notas de alcance

- La máquina de la admisionista (operador) funciona como servidor local y aloja
  backend, frontend y base de datos.
- La máquina del supervisor y las demás estaciones funcionan como clientes y
  utilizan el frontend publicado por el servidor local.
- Los datos clínicos y administrativos no deben salir de la máquina servidor ni
  de la red local autorizada por el centro de salud.
- No se incluyeron como requisitos confirmados el envío de SMS/WhatsApp ni la
  alta disponibilidad, porque no forman parte de este despliegue local.
- Las metas numéricas de rendimiento son propuestas iniciales y deben validarse
  con el equipo de TI antes de convertirlas en criterios contractuales.