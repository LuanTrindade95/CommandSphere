import { ComponentFixture, TestBed } from '@angular/core/testing';

import { UiIconComponent } from './ui-icon.component';

describe('UiIconComponent', () => {
  let fixture: ComponentFixture<UiIconComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UiIconComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UiIconComponent);
    fixture.componentRef.setInput('name', 'search');
  });

  it('never renders an inline style attribute, only a size class', () => {
    fixture.componentRef.setInput('size', 22);
    fixture.detectChanges();

    const span = fixture.nativeElement.querySelector('span');

    expect(span.getAttribute('style')).toBeNull();
    expect(span.className).toContain('w-[22px]');
    expect(span.className).toContain('h-[22px]');
  });

  it('falls back to the default 18px class for a size outside the known set', () => {
    fixture.componentRef.setInput('size', 999);
    fixture.detectChanges();

    const span = fixture.nativeElement.querySelector('span');

    expect(span.getAttribute('style')).toBeNull();
    expect(span.className).toContain('w-[18px]');
    expect(span.className).toContain('h-[18px]');
  });

  it('renders the requested icon svg', () => {
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('svg')).not.toBeNull();
  });
});
