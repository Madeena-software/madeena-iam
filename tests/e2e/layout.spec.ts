import { test, expect } from '@playwright/test';

test.use({ viewport: { width: 1280, height: 1000 } });

test('print layout details at 1000px height with precise footer selector', async ({ page }) => {
  console.log('Navigating to login...');
  await page.goto('http://127.0.0.1:8000/admin/login');
  await page.fill('input[type="email"]', 'admin@madeena.local');
  await page.fill('input[type="password"]', 'admin');
  await page.keyboard.press('Enter');
  await page.waitForURL(url => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'));
  await page.waitForLoadState('networkidle');

  const results = await page.evaluate(() => {
    function getDetails(selector: string) {
      const el = document.querySelector(selector);
      if (!el) return { selector, exists: false };
      const rect = el.getBoundingClientRect();
      const style = window.getComputedStyle(el);
      return {
        selector,
        exists: true,
        rect: {
          top: rect.top,
          bottom: rect.bottom,
          left: rect.left,
          right: rect.right,
          width: rect.width,
          height: rect.height,
        },
        styles: {
          display: style.display,
          flexDirection: style.flexDirection,
          flexGrow: style.flexGrow,
          position: style.position,
          marginTop: style.marginTop,
          marginBottom: style.marginBottom,
          height: style.height,
          minHeight: style.minHeight,
        }
      };
    }

    // Find the leaf-most div that contains the footer text
    const allDivs = Array.from(document.querySelectorAll('.fi-main-ctn div'));
    const footer = allDivs.find(div => {
      // It has the text and it has direct children that are not layout divs
      return div.textContent.includes('Madeena. All rights reserved.') && 
             div.classList.contains('border-t');
    });

    let footerDetails = null;
    if (footer) {
      const rect = footer.getBoundingClientRect();
      const style = window.getComputedStyle(footer);
      footerDetails = {
        className: footer.className,
        rect: {
          top: rect.top,
          bottom: rect.bottom,
          height: rect.height,
        },
        styles: {
          display: style.display,
          marginTop: style.marginTop,
          position: style.position,
        },
        parent: footer.parentElement ? {
          tagName: footer.parentElement.tagName,
          className: footer.parentElement.className,
          display: window.getComputedStyle(footer.parentElement).display,
          flexDirection: window.getComputedStyle(footer.parentElement).flexDirection,
          height: window.getComputedStyle(footer.parentElement).height,
          minHeight: window.getComputedStyle(footer.parentElement).minHeight,
        } : null
      };
    }

    return {
      viewport: {
        width: window.innerWidth,
        height: window.innerHeight,
      },
      fiMainCtn: getDetails('.fi-main-ctn'),
      fiMain: getDetails('.fi-main'),
      footer: footerDetails,
    };
  });

  console.log('LAYOUT RESULTS WITH PRECISE FOOTER:', JSON.stringify(results, null, 2));
});
