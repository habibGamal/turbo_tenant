import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: hero-banners (Live)', () => {
  test('renders hero banner slider on home page', async ({ page, theme }) => {
    await page.goto('/');

    const heroSlider = theme.hero.getHeroSlider();
    if (await heroSlider.isVisible()) {
      const slides = theme.hero.getSlides();
      const slideCount = await slides.count();

      if (slideCount > 0) {
        await expect(slides.first()).toBeVisible();

        // Check if slide CTA exists
        const cta = theme.hero.getSlideCta(slides.first());
        if (await cta.isVisible()) {
          const text = await cta.textContent();
          expect(text?.trim().length).toBeGreaterThan(0);
        }
      }
    }
  });
});
