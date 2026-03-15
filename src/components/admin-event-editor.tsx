import { useEffect, useRef, useState } from 'react'
import type { AdminEventRow, AdminRuntime, EventRepeatValue } from '../types.ts'

const ADMIN_SYNC_EVENT = 'post-calendar-admin-sync'

const WEEKDAYS = [
  { code: 'MO', key: 'monday' },
  { code: 'TU', key: 'tuesday' },
  { code: 'WE', key: 'wednesday' },
  { code: 'TH', key: 'thursday' },
  { code: 'FR', key: 'friday' },
  { code: 'SA', key: 'saturday' },
  { code: 'SU', key: 'sunday' },
] as const

function createEmptyRow(): AdminEventRow {
  return {
    label: '',
    all_day: false,
    start_date: '',
    end_date: '',
    repeat: 'none',
    repeat_interval: 1,
    repeat_byday: [],
    repeat_until: '',
  }
}

function toInputValue(value: string, allDay: boolean): string {
  if (!value) {
    return ''
  }

  if (allDay) {
    return value.slice(0, 10)
  }

  return value.replace(' ', 'T').slice(0, 16)
}

function fromInputValue(value: string, allDay: boolean, isEnd: boolean): string {
  if (!value) {
    return ''
  }

  if (allDay) {
    return `${value} ${isEnd ? '23:59:59' : '00:00:00'}`
  }

  if (!value.includes('T')) {
    return `${value} ${isEnd ? '23:59:59' : '00:00:00'}`
  }

  return `${value.replace('T', ' ')}:00`
}

function toggleAllDayRow(row: AdminEventRow, allDay: boolean): AdminEventRow {
  if (!allDay) {
    return {
      ...row,
      all_day: false,
    }
  }

  const startDate = row.start_date ? row.start_date.slice(0, 10) : ''
  const endDate = row.end_date ? row.end_date.slice(0, 10) : startDate

  return {
    ...row,
    all_day: true,
    start_date: startDate ? `${startDate} 00:00:00` : '',
    end_date: endDate ? `${endDate} 23:59:59` : '',
  }
}

function normalizeRows(rows: AdminEventRow[] | undefined): AdminEventRow[] {
  if (!Array.isArray(rows)) {
    return []
  }

  return rows.map((row) => ({
    label: row?.label ?? '',
    all_day: Boolean(row?.all_day),
    start_date: row?.start_date ?? '',
    end_date: row?.end_date ?? '',
    repeat: (row?.repeat ?? 'none') as EventRepeatValue,
    repeat_interval: Math.max(1, Number(row?.repeat_interval ?? 1) || 1),
    repeat_byday: Array.isArray(row?.repeat_byday) ? row.repeat_byday : [],
    repeat_until: row?.repeat_until ?? '',
  }))
}

function getSharedRows(runtime: AdminRuntime): AdminEventRow[] {
  const sharedRows = globalThis.PostCalendarAdminSharedRows

  if (Array.isArray(sharedRows)) {
    return normalizeRows(sharedRows)
  }

  return normalizeRows(runtime.currentEvents)
}

function broadcastRows(rows: AdminEventRow[]): void {
  globalThis.PostCalendarAdminSharedRows = rows
  globalThis.dispatchEvent(new CustomEvent(ADMIN_SYNC_EVENT, { detail: rows }))
}

export default function AdminEventEditor({ runtime }: { runtime: AdminRuntime }) {
  const instanceId = useRef(`post-calendar-admin-${Math.random().toString(36).slice(2)}`)
  const [rows, setRows] = useState<AdminEventRow[]>(() => getSharedRows(runtime))
  const fieldName = runtime.fieldName ?? 'post_calendar_events'
  const strings = runtime.strings ?? {}

  useEffect(() => {
    if (!Array.isArray(globalThis.PostCalendarAdminSharedRows)) {
      globalThis.PostCalendarAdminSharedRows = rows
      return
    }

    setRows(normalizeRows(globalThis.PostCalendarAdminSharedRows))
  }, [])

  useEffect(() => {
    function handleSync(event: Event): void {
      const customEvent = event as CustomEvent<AdminEventRow[]>

      if (!Array.isArray(customEvent.detail)) {
        return
      }

      setRows(normalizeRows(customEvent.detail))
    }

    globalThis.addEventListener(ADMIN_SYNC_EVENT, handleSync)

    return () => {
      globalThis.removeEventListener(ADMIN_SYNC_EVENT, handleSync)
    }
  }, [])

  function syncRows(updater: (currentRows: AdminEventRow[]) => AdminEventRow[]): void {
    setRows((currentRows) => {
      const nextRows = updater(currentRows)
      broadcastRows(nextRows)
      return nextRows
    })
  }

  function updateRow(index: number, nextRow: AdminEventRow): void {
    syncRows((currentRows) => currentRows.map((row, rowIndex) => (rowIndex === index ? nextRow : row)))
  }

  function appendRow(): void {
    syncRows((currentRows) => [...currentRows, createEmptyRow()])
  }

  function removeRow(index: number): void {
    syncRows((currentRows) => currentRows.filter((_, rowIndex) => rowIndex !== index))
  }

  return (
    <div className="post-calendar-admin-editor" data-post-calendar-admin-instance={instanceId.current}>
      {rows.map((row, index) => (
        <div key={`hidden-${index}`} hidden>
          <input type="hidden" name={`${fieldName}[${index}][label]`} value={row.label} readOnly />
          <input type="hidden" name={`${fieldName}[${index}][all_day]`} value={row.all_day ? '1' : '0'} readOnly />
          <input type="hidden" name={`${fieldName}[${index}][start_date]`} value={row.start_date} readOnly />
          <input type="hidden" name={`${fieldName}[${index}][end_date]`} value={row.end_date} readOnly />
          <input type="hidden" name={`${fieldName}[${index}][repeat]`} value={row.repeat} readOnly />
          <input type="hidden" name={`${fieldName}[${index}][repeat_interval]`} value={String(row.repeat_interval)} readOnly />
          <input type="hidden" name={`${fieldName}[${index}][repeat_until]`} value={row.repeat_until} readOnly />
          {row.repeat_byday.map((weekday, weekdayIndex) => (
            <input
              key={`${index}-${weekday}-${weekdayIndex}`}
              type="hidden"
              name={`${fieldName}[${index}][repeat_byday][]`}
              value={weekday}
              readOnly
            />
          ))}
        </div>
      ))}

      <p className="post-calendar-admin-editor__intro">{strings.eventsIntro ?? 'Add one or more event rows to make this post appear in the calendar.'}</p>

      {rows.length === 0 ? <p className="post-calendar-admin-editor__empty">{strings.noEvents ?? 'No event rows yet.'}</p> : null}

      <div className="post-calendar-admin-editor__list">
        {rows.map((row, index) => {
          const isRepeating = row.repeat !== 'none'
          const isWeekly = row.repeat === 'weekly'

          return (
            <section key={index} className="post-calendar-admin-editor__card">
              <div className="post-calendar-admin-editor__card-header">
                <h3 className="post-calendar-admin-editor__card-title">{`${strings.eventNumber ?? 'Event'} ${index + 1}`}</h3>
                <button type="button" className="button button-secondary" onClick={() => removeRow(index)}>
                  {strings.removeEvent ?? 'Remove event'}
                </button>
              </div>

              <div className="post-calendar-admin-editor__card-body">
                <label className="post-calendar-admin-editor__field">
                  <span>{strings.eventLabel ?? 'Event label'}</span>
                  <input
                    type="text"
                    value={row.label}
                    onChange={(event) => updateRow(index, { ...row, label: event.target.value })}
                  />
                  <span className="post-calendar-admin-editor__help">{strings.eventLabelHelp ?? 'Leave empty to use the post title.'}</span>
                </label>

                <div className="post-calendar-admin-editor__grid">
                  <label className="post-calendar-admin-editor__field">
                    <span>{strings.startDate ?? 'Start date'}</span>
                    <input
                      type={row.all_day ? 'date' : 'datetime-local'}
                      value={toInputValue(row.start_date, row.all_day)}
                      onChange={(event) => updateRow(index, { ...row, start_date: fromInputValue(event.target.value, row.all_day, false) })}
                    />
                  </label>

                  <label className="post-calendar-admin-editor__field">
                    <span>{strings.endDate ?? 'End date'}</span>
                    <input
                      type={row.all_day ? 'date' : 'datetime-local'}
                      value={toInputValue(row.end_date, row.all_day)}
                      onChange={(event) => updateRow(index, { ...row, end_date: fromInputValue(event.target.value, row.all_day, true) })}
                    />
                  </label>

                  <label className="post-calendar-admin-editor__field post-calendar-admin-editor__checkbox">
                    <input
                      type="checkbox"
                      checked={row.all_day}
                      onChange={(event) => updateRow(index, toggleAllDayRow(row, event.target.checked))}
                    />
                    <span>{strings.allDay ?? 'All-day event'}</span>
                  </label>

                  <label className="post-calendar-admin-editor__field">
                    <span>{strings.eventRepeat ?? 'Event frequency'}</span>
                    <select
                      value={row.repeat}
                      onChange={(event) => updateRow(index, {
                        ...row,
                        repeat: event.target.value as EventRepeatValue,
                        repeat_byday: event.target.value === 'weekly' ? row.repeat_byday : [],
                        repeat_until: event.target.value === 'none' ? '' : row.repeat_until,
                      })}
                    >
                      <option value="none">{strings.doesNotRepeat ?? 'Does not repeat'}</option>
                      <option value="weekly">{strings.weekly ?? 'Weekly'}</option>
                      <option value="monthly">{strings.monthly ?? 'Monthly'}</option>
                      <option value="yearly">{strings.yearly ?? 'Yearly'}</option>
                    </select>
                  </label>
                </div>

                {isRepeating ? (
                  <div className="post-calendar-admin-editor__grid">
                    <label className="post-calendar-admin-editor__field">
                      <span>{strings.repeatInterval ?? 'Repeat interval'}</span>
                      <input
                        type="number"
                        min={1}
                        step={1}
                        value={row.repeat_interval}
                        onChange={(event) => updateRow(index, { ...row, repeat_interval: Math.max(1, Number(event.target.value) || 1) })}
                      />
                      <span className="post-calendar-admin-editor__help">{strings.repeatIntervalHelp ?? 'For example, every 2 weeks.'}</span>
                    </label>

                    <label className="post-calendar-admin-editor__field">
                      <span>{strings.repeatUntil ?? 'Repeat until'}</span>
                      <input
                        type={row.all_day ? 'date' : 'datetime-local'}
                        value={toInputValue(row.repeat_until, row.all_day)}
                        onChange={(event) => updateRow(index, { ...row, repeat_until: fromInputValue(event.target.value, row.all_day, true) })}
                      />
                    </label>
                  </div>
                ) : null}

                {isWeekly ? (
                  <div className="post-calendar-admin-editor__field">
                    <span>{strings.repeatOn ?? 'Repeat on'}</span>
                    <div className="post-calendar-admin-editor__weekdays">
                      {WEEKDAYS.map((weekday) => {
                        const checked = row.repeat_byday.includes(weekday.code)

                        return (
                          <label key={weekday.code} className="post-calendar-admin-editor__weekday">
                            <input
                              type="checkbox"
                              checked={checked}
                              onChange={(event) => updateRow(index, {
                                ...row,
                                repeat_byday: event.target.checked
                                  ? [...row.repeat_byday, weekday.code]
                                  : row.repeat_byday.filter((value) => value !== weekday.code),
                              })}
                            />
                            <span>{strings[weekday.key] ?? weekday.code}</span>
                          </label>
                        )
                      })}
                    </div>
                  </div>
                ) : null}
              </div>
            </section>
          )
        })}
      </div>

      <div className="post-calendar-admin-editor__actions">
        <button type="button" className="button button-primary" onClick={appendRow}>
          {strings.addEvent ?? 'Add event'}
        </button>
      </div>
    </div>
  )
}
