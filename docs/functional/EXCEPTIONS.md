# Flujos de excepción (Fase 1)

| # | Situación | Comportamiento del sistema |
|---|---|---|
| E1 | **Muestra rechazada** | Motivo de catálogo + texto; cancela actividades abiertas y las retira de las tandas (registrado en el historial de cada tanda); `REQUEST_NEW_SAMPLE` opcional; aparece en el informe como "muestra rechazada" si la configuración lo indica |
| E2 | **Estudio agregado tarde** | Se agrega un SampleStudy a la muestra ya en proceso → nuevo `INOCULATE`; no afecta lo ya hecho |
| E3 | **Estudio anulado** | Se cancelan sus actividades con motivo; si ya había informes, se exige corrección |
| E4 | **Placa perdida o rota** | Plate → LOST; se propone volver a sembrar (`INOCULATE` attempt+1) si queda material |
| E5 | **Reincubación** | READ → reincubar → nueva lectura programada; el máximo de reincubaciones sale de la configuración; al superarlo, se avisa |
| E6 | **Cultivo polimicrobiano** | N aislamientos; cada uno con su flujo; el estudio se cierra cuando todos están resueltos. El protocolo puede limitar a cuántos se les hace identificación o antibiograma (⚙ config, sin inventar criterios) |
| E7 | **Identificación no concluyente** | Intento INCONCLUSIVE → la UI ofrece: otro método (crea un nuevo intento), pruebas adicionales, repique o derivación externa (❓ Q-F5); hasta resolverlo, el aislamiento no habilita antibiograma, salvo que la configuración lo permita |
| E8 | **Antibiograma discordante** | Automático ≠ revisado, o discordancia entre métodos o con resistencia intrínseca ⚙ config → el resultado se marca, se exige motivo y queda destacado en la validación |
| E9 | **Repetición de antibiograma** | Nuevo test `repeat_of_id`; el anterior pasa a SUPERSEDED y se conserva visible en el historial |
| E10 | **Resultado crítico** | Alerta + actividad prioritaria + barra persistente; puede generar informe preliminar ⚙ config |
| E11 | **Corrección de informe** | Nueva versión DRAFT con motivo; la anterior pasa a SUPERSEDED; el PDF nuevo indica "Informe corregido — reemplaza a v1"; si el LIS está integrado, se reenvía |
| E12 | **Tanda interrumpida** | Pausa; los ítems hechos quedan confirmados; se retoma en el primer pendiente (ver WORK_BATCHES.md §4) |
| E13 | **Dos usuarios sobre la misma muestra** | Las actividades distintas de una misma muestra se pueden hacer en paralelo. Para una misma actividad: el `claim` con bloqueo impide que dos personas la tomen; si una entidad cambió mientras otro la editaba, `lock_version` produce un conflicto y la UI muestra "Juan modificó esta lectura a las 10:42", con sus cambios y la opción de recargar |
| E14 | **Doble envío / doble clic / reintento de red** | `idempotency_key`: la segunda solicitud devuelve el resultado de la primera, sin duplicar |
| E15 | **Falla de integración** | El mensaje queda en `integration_messages` con estado de error y reintentos con espera creciente; aviso visible al Administrador y en la tarjeta de la muestra afectada; nunca bloquea el trabajo manual |
| E16 | **Cambio de turno** | Pantalla "Traspaso de turno": tandas abiertas (se pueden transferir a otra persona con auditoría), críticos sin comunicar, demoradas, previsto para el siguiente turno; nota de traspaso opcional |
| E17 | **Usuario sin sesión a mitad de tanda** | La tanda queda IN_PROGRESS; los borradores del ítem en edición se guardan localmente y en el servidor (autoguardado); al volver, se retoma |
| E18 | **Configuración clínica cambia durante el proceso** | Los casos en curso siguen con la versión congelada; la migración a la nueva versión es explícita y auditada (solo Director) |
| E19 | **Lector de barras lee un código desconocido o de otra tanda** | Mensaje claro, sin acción automática; ofrece buscar la muestra o explica dónde está ("Está en la tanda LEC-…-02 de Ana") |
