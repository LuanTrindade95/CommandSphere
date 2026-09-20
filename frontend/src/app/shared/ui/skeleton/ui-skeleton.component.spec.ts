import { ComponentFixture, TestBed } from '@angular/core/testing';

import { UiSkeletonComponent } from './ui-skeleton.component';

describe('UiSkeletonComponent', () => {
  let fixture: ComponentFixture<UiSkeletonComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UiSkeletonComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UiSkeletonComponent);
  });

  it('never renders an inline style attribute, using the default size classes', () => {
    fixture.detectChanges();

    const span = fixture.nativeElement.querySelector('span');

    expect(span.getAttribute('style')).toBeNull();
    expect(span.className).toContain('w-full');
    expect(span.className).toContain('h-[1rem]');
  });

  it('maps a known width/height pair to its arbitrary-value classes', () => {
    fixture.componentRef.setInput('width', '70%');
    fixture.componentRef.setInput('height', '1.25rem');
    fixture.detectChanges();

    const span = fixture.nativeElement.querySelector('span');

    expect(span.getAttribute('style')).toBeNull();
    expect(span.className).toContain('w-[70%]');
    expect(span.className).toContain('h-[1.25rem]');
  });

  it('falls back to the default classes for an unmapped width/height', () => {
    fixture.componentRef.setInput('width', '33%');
    fixture.componentRef.setInput('height', '5rem');
    fixture.detectChanges();

    const span = fixture.nativeElement.querySelector('span');

    expect(span.getAttribute('style')).toBeNull();
    expect(span.className).toContain('w-full');
    expect(span.className).toContain('h-[1rem]');
  });
});
