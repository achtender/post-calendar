/// <reference types="vite/client" />

import type { CalendarRuntime } from './types.ts';

declare global {
  interface Window {
    PostCalendarRuntime?: CalendarRuntime;
  }
}

export {};