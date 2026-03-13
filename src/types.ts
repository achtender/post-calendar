export interface CalendarConfig {
  defaultView?: string;
  enabledViews?: string[];
  showToolbar?: boolean;
  agendaRangeMode?: string;
  agendaRangeMonths?: number | string;
  postTypes?: string[];
  error?: string;
}

export interface CalendarEventInput {
  id?: number | string;
  title: string;
  start: string | Date;
  end: string | Date;
  allDay?: boolean;
  url?: string;
  excerpt?: string;
  tags?: string[];
}

export interface CalendarEventRecord extends Omit<CalendarEventInput, 'start' | 'end'> {
  start: Date;
  end: Date;
  allDay: boolean;
  tags: string[];
}

export interface CalendarRuntime {
  restUrl?: string;
  restNonce?: string;
  locale?: string;
  previewEvents?: CalendarEventInput[];
  strings?: CalendarRuntimeStrings;
}

export interface CalendarRuntimeStrings {
  allDay?: string;
  agenda?: string;
  back?: string;
  calendarViews?: string;
  configParseError?: string;
  date?: string;
  day?: string;
  event?: string;
  loadError?: string;
  missingApiUrl?: string;
  month?: string;
  next?: string;
  noEvents?: string;
  showMore?: string;
  time?: string;
  today?: string;
  week?: string;
}

export interface CalendarRange {
  start: Date;
  end: Date;
}