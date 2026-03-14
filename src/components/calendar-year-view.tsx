import { useMemo } from 'react';
import { Navigate } from 'react-big-calendar';
import moment from 'moment';
import type { CalendarEventRecord, CalendarRange } from '../types.ts';
import { getRuntimeStrings } from './calendar-state.ts';

const MONTH_EVENT_LIMIT = 4;

interface YearMonthSummary {
  events: CalendarEventRecord[];
  isCurrentMonth: boolean;
  label: string;
  start: Date;
}

interface CalendarYearViewProps {
  date: Date;
  events: CalendarEventRecord[];
  onYearMonthSelect?: (date: Date) => void;
  strings?: {
    showMore?: string;
    showMoreEventsForMonth?: string;
  };
}

function formatYearMoreAriaLabel(template: string, count: number, monthLabel: string): string {
  return template.replace('%1$s', String(count)).replace('%2$s', monthLabel);
}

function buildYearRange(date: Date): CalendarRange {
  const start = moment(date).startOf('year');

  return {
    start: start.toDate(),
    end: start.clone().endOf('year').toDate(),
  };
}

function buildMonthSummaries(date: Date, events: CalendarEventRecord[]): YearMonthSummary[] {
  const activeYear = moment(date).year();
  const currentMonth = moment();

  return Array.from({ length: 12 }, (_, monthIndex) => {
    const monthStart = moment({ year: activeYear, month: monthIndex, day: 1 }).startOf('month');
    const monthEnd = monthStart.clone().endOf('month');
    const monthEvents = events
      .filter((event) => moment(event.start).isSameOrBefore(monthEnd) && moment(event.end).isSameOrAfter(monthStart))
      .sort((left, right) => left.start.getTime() - right.start.getTime());

    return {
      events: monthEvents,
      isCurrentMonth: currentMonth.isSame(monthStart, 'month'),
      label: monthStart.format('MMMM'),
      start: monthStart.toDate(),
    };
  });
}

function chunkMonths(months: YearMonthSummary[], chunkSize: number): YearMonthSummary[][] {
  return months.reduce<YearMonthSummary[][]>((rows, month, monthIndex) => {
    const rowIndex = Math.floor(monthIndex / chunkSize);

    if (!rows[rowIndex]) {
      rows[rowIndex] = [];
    }

    rows[rowIndex].push(month);

    return rows;
  }, []);
}

const CalendarYearView = Object.assign(
  function CalendarYearView({ date, events, onYearMonthSelect, strings: runtimeStrings }: CalendarYearViewProps) {
    const months = useMemo(() => buildMonthSummaries(date, events), [date, events]);
    const monthRows = useMemo(() => chunkMonths(months, 4), [months]);
    const strings = getRuntimeStrings({ strings: runtimeStrings });

    return (
      <div className="post-calendar-year-view">
        <table className="post-calendar-year-table">
          <tbody>
            {monthRows.map((row, rowIndex) => (
              <tr key={`year-row-${rowIndex}`} className="post-calendar-year-row">
                {row.map((month) => {
                  const visibleEvents = month.events.slice(0, MONTH_EVENT_LIMIT);
                  const hiddenCount = month.events.length - visibleEvents.length;

                  return (
                    <td
                      key={month.start.toISOString()}
                      className={`post-calendar-year-cell${month.isCurrentMonth ? ' is-current' : ''}`}
                    >
                      <div className="post-calendar-year-cell-inner">
                        <div className="post-calendar-year-cell-header">
                          <button
                            type="button"
                            className="post-calendar-year-month-button"
                            onClick={() => onYearMonthSelect?.(month.start)}
                            aria-label={`${month.label} ${moment(month.start).format('YYYY')}`}
                          >
                            {month.label}
                          </button>
                        </div>

                        <div className="post-calendar-year-events">
                          {visibleEvents.map((event) => (
                            <span key={String(event.id)} className="post-calendar-event-pill">
                              {event.url ? (
                                <a href={event.url} className="post-calendar-event-link">
                                  {event.title}
                                </a>
                              ) : (
                                <span>{event.title}</span>
                              )}
                            </span>
                          ))}

                          {hiddenCount > 0 ? (
                            <button
                              type="button"
                              className="post-calendar-year-more-button"
                              onClick={() => onYearMonthSelect?.(month.start)}
                              aria-label={formatYearMoreAriaLabel(strings.showMoreEventsForMonth, hiddenCount, month.label)}
                            >
                              +{hiddenCount} {strings.showMore}
                            </button>
                          ) : null}
                        </div>
                      </div>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  },
  {
    navigate: (date: Date, action: string): Date => {
      switch (action) {
        case Navigate.PREVIOUS:
          return moment(date).subtract(1, 'year').toDate();
        case Navigate.NEXT:
          return moment(date).add(1, 'year').toDate();
        default:
          return date;
      }
    },
    range: (date: Date): CalendarRange => buildYearRange(date),
    title: (date: Date): string => moment(date).format('YYYY'),
  }
);

export default CalendarYearView;