import { Injectable, signal } from '@angular/core';

export type ToastTone = 'success' | 'danger' | 'info';

export interface ToastMessage {
  id: number;
  translationKey: string;
  tone: ToastTone;
}

@Injectable({ providedIn: 'root' })
export class ToastService {
  private readonly messagesState = signal<ToastMessage[]>([]);
  private nextId = 1;

  readonly messages = this.messagesState.asReadonly();

  success(translationKey: string): void {
    this.push(translationKey, 'success');
  }

  danger(translationKey: string): void {
    this.push(translationKey, 'danger');
  }

  info(translationKey: string): void {
    this.push(translationKey, 'info');
  }

  dismiss(id: number): void {
    this.messagesState.update((messages) => messages.filter((message) => message.id !== id));
  }

  private push(translationKey: string, tone: ToastTone): void {
    const id = this.nextId;
    this.nextId += 1;
    this.messagesState.update((messages) => [...messages, { id, translationKey, tone }]);
  }
}
