import { Page } from '@playwright/test';
import { BaseThemeAdapter } from '../base/BaseThemeAdapter';

/**
 * Default Theme Adapter.
 * Inherits the standard semantic and data-testid contracts from BaseThemeAdapter.
 * Override any selectors here if the default theme renders specific DOM structures.
 */
export class DefaultThemeAdapter extends BaseThemeAdapter {
  override readonly name = 'default';

  constructor(page: Page) {
    super(page);
  }
}
