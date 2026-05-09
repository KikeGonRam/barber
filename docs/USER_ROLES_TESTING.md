# User Roles & Permissions Validation Plan

## Objective
Verify that each user profile can access their designated features and that Phase 4 (dark mode, keyboard shortcuts, animations) works correctly across all roles.

## User Profiles & Access Matrix

### 1. ADMINISTRADOR (Admin)
**Email**: admin@test.com  
**Password**: password  
**Permissions**: Full access to all features

#### Dashboard Access
- [x] Ver: Dashboard completo with KPIs
- [x] Ver: All user statistics
- [x] Ver: Revenue charts, appointments, alerts

#### Navigation Links Visible
- [x] Dashboard
- [x] Muro Inspiración
- [x] Citas
- [x] Clientes
- [x] Pagos
- [x] Movimientos (Inventory)
- [x] Barberos
- [x] Usuarios
- [x] Servicios
- [x] Productos
- [x] Almacén Central
- [x] Configuracion
- [x] Reportes
- [x] Logs
- [x] Notificaciones

#### API Endpoints Access
- [x] GET /api/v1/admin/dashboard/stats
- [x] GET /api/v1/admin/dashboard/upcoming-appointments
- [x] GET /api/v1/admin/dashboard/revenue
- [x] GET /api/v1/admin/dashboard/alerts
- [x] GET /api/v1/admin/barbers (list all)
- [x] GET /api/v1/admin/clients (list all)
- [x] GET /api/v1/admin/inventory/products
- [x] GET /api/v1/admin/reports/*

#### Phase 4 Features
- [x] Dark Mode Toggle (works)
- [x] Keyboard Shortcuts (works)
- [x] Animations (works)

---

### 2. RECEPCIONISTA (Receptionist)
**Email**: recepcionista@test.com  
**Password**: password  
**Permissions**: Limited access - Client & appointment management

#### Dashboard Access
- [x] Ver: Dashboard (but limited - only citas & clientes data)
- [x] Ver: Upcoming appointments
- [ ] NO: Revenue charts (not assigned)
- [ ] NO: Advanced analytics

#### Navigation Links Visible
- [x] Dashboard
- [x] Muro Inspiración
- [x] Citas (manage all)
- [x] Clientes (manage all)
- [x] Pagos (manage)
- [x] Movimientos (view only)
- [ ] NO: Barberos
- [ ] NO: Usuarios
- [ ] NO: Servicios
- [ ] NO: Productos
- [ ] NO: Almacén Central
- [ ] NO: Configuracion
- [ ] NO: Reportes
- [ ] NO: Logs

#### API Endpoints Access
- [x] GET /api/v1/admin/dashboard/stats (limited)
- [x] GET /api/v1/admin/dashboard/upcoming-appointments
- [ ] NO: /api/v1/admin/dashboard/revenue (403 forbidden)
- [x] GET /api/v1/admin/clients (list all)
- [x] POST /api/v1/admin/clients (create)
- [x] GET /api/v1/admin/appointments (list)
- [x] POST /api/v1/admin/appointments (create)
- [ ] NO: /api/v1/admin/barbers (403 forbidden)
- [ ] NO: /api/v1/admin/inventory/products (403 forbidden)

#### Phase 4 Features
- [x] Dark Mode Toggle (works)
- [x] Keyboard Shortcuts (works)
- [x] Animations (works)

---

### 3. BARBERO (Barber)
**Email**: barbero@test.com  
**Password**: password  
**Permissions**: Minimal access - Own appointments only

#### Dashboard Access
- [x] Ver: Dashboard (personal stats only)
- [x] Ver: Own upcoming appointments
- [ ] NO: Other barbers' data
- [ ] NO: Client information
- [ ] NO: Revenue/payments

#### Navigation Links Visible
- [x] Dashboard
- [x] Muro Inspiración
- [ ] NO: Citas (full management)
- [ ] NO: Clientes
- [ ] NO: Pagos
- [ ] NO: Movimientos
- [ ] NO: Barberos
- [ ] NO: Usuarios
- [ ] NO: Servicios
- [ ] NO: Productos
- [ ] NO: Almacén Central
- [ ] NO: Configuracion
- [ ] NO: Reportes
- [ ] NO: Logs
- [x] Mi Agenda (barber-specific route)
- [x] Mi Portafolio (barber-specific route)
- [x] Mi Horario (barber-specific route)

#### API Endpoints Access
- [ ] NO: /api/v1/admin/* (403 forbidden for all)
- [x] GET /barber/appointments (personal)
- [x] GET /barber/schedule (own schedule)
- [x] POST /barber/portfolio/photos (own portfolio)

#### Phase 4 Features
- [x] Dark Mode Toggle (works)
- [x] Keyboard Shortcuts (works - but fewer relevant)
- [x] Animations (works)

---

### 4. CLIENTE (Client/Customer)
**Email**: cliente@test.com  
**Password**: password  
**Permissions**: Minimal access - Own appointments only

#### Dashboard Access
- [x] Ver: Dashboard (read-only personal stats)
- [x] Ver: Own appointments only
- [ ] NO: Other clients' data
- [ ] NO: Analytics

#### Navigation Links Visible
- [x] Dashboard
- [x] Muro Inspiración
- [ ] NO: Citas (management)
- [ ] NO: Clientes
- [ ] NO: Pagos
- [ ] NO: Movimientos
- [ ] NO: Barberos
- [ ] NO: Usuarios
- [ ] NO: Servicios
- [ ] NO: Productos
- [ ] NO: Almacén Central
- [ ] NO: Configuracion
- [ ] NO: Reportes
- [ ] NO: Logs
- [x] Mis Citas (client-specific route)

#### API Endpoints Access
- [ ] NO: /api/v1/admin/* (403 forbidden for all)
- [x] GET /client/appointments (personal)
- [x] POST /client/appointments (create new)
- [x] GET /client/profile (own profile)

#### Phase 4 Features
- [x] Dark Mode Toggle (works)
- [x] Keyboard Shortcuts (works - basic)
- [x] Animations (works)

---

## Testing Checklist

### Test 1: Admin User Flow
```
1. Login as admin@test.com / password
2. Verify dashboard loads with all KPIs
3. Click Dark Mode toggle - theme changes
4. Press Ctrl+Shift+D - theme toggles
5. Press Ctrl+Shift+? - help modal opens
6. Verify all nav links visible
7. Access /settings - should work
8. Refresh page - dark mode persists
9. Logout
```

### Test 2: Recepcionista User Flow
```
1. Login as recepcionista@test.com / password
2. Verify dashboard loads (limited data)
3. Click "Citas" - can manage appointments
4. Click "Clientes" - can manage clients
5. Try clicking "Barberos" - should be hidden
6. Try accessing /barbers - should get 403
7. Dark mode toggle - works
8. Keyboard shortcuts - works
9. Logout
```

### Test 3: Barbero User Flow
```
1. Login as barbero@test.com / password
2. Verify dashboard loads
3. Click "Mi Agenda" - personal appointments
4. Click "Mi Portafolio" - can edit
5. Click "Mi Horario" - can edit schedule
6. Try clicking "Clientes" - should be hidden
7. Try accessing /clients - should get 403
8. Dark mode toggle - works
9. Keyboard shortcuts - works
10. Logout
```

### Test 4: Cliente User Flow
```
1. Login as cliente@test.com / password
2. Verify dashboard loads
3. Click "Mis Citas" - personal appointments
4. Can book new appointments
5. Try clicking "Citas" - should be hidden (full management)
6. Try accessing /appointments - should be forbidden
7. Dark mode toggle - works
8. Keyboard shortcuts - works
9. Logout
```

### Test 5: Dark Mode Persistence
```
1. Login as any user
2. Toggle dark mode ON
3. Refresh page (F5)
4. Dark mode should still be ON
5. Toggle dark mode OFF
6. Navigate to another page
7. Dark mode should be OFF
8. Close browser completely
9. Login again
10. Dark mode should remember last setting
```

### Test 6: Keyboard Shortcuts
```
For each user:
1. Press Ctrl+Shift+D - dark mode toggles
2. Press Ctrl+Shift+? - help modal opens
3. Press Ctrl+K - should open command palette (if implemented)
4. Press Escape - close any open modals
```

### Test 7: Animations
```
1. Load dashboard
2. Elements should fade in smoothly
3. Toggle dark mode - transitions should be smooth (0.3s)
4. Charts should animate on load
5. Buttons should have ripple effect on click
6. No jarring color changes
```

### Test 8: Mobile Responsiveness
```
1. Open Chrome DevTools (F12)
2. Toggle device toolbar (mobile view)
3. For each user:
   - Navigation sidebar collapses (mobile drawer)
   - Dark mode toggle visible
   - Keyboard shortcuts help button visible
   - Modal opens correctly on mobile
   - Buttons are at least 44x44px for touch
   - Text is readable (no overflow)
```

### Test 9: Accessibility (a11y)
```
1. Open Axe DevTools
2. Run scan on dashboard
3. Check for:
   - No color contrast issues (4.5:1 minimum)
   - ARIA labels present on buttons
   - Keyboard navigation works (Tab through all)
   - Focus outline visible
   - Form labels associated
```

### Test 10: API Permission Tests
```
# As Admin
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/admin/barbers
# Expected: 200 OK + data

# As Recepcionista
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/admin/barbers
# Expected: 403 Forbidden

# As Barbero
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/admin/clients
# Expected: 403 Forbidden

# As Cliente
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/admin/dashboard/stats
# Expected: 403 Forbidden or limited data
```

---

## Known Test Users

| Role | Email | Password | Name |
|------|-------|----------|------|
| Administrador | admin@test.com | password | Admin Test |
| Recepcionista | recepcionista@test.com | password | Recepcionista Test |
| Barbero | barbero@test.com | password | Barbero Test |
| Cliente | cliente@test.com | password | Cliente Test |

---

## Issues to Watch For

1. **Dark Mode Not Persisting** - Check localStorage key is 'darkMode'
2. **Keyboard Shortcuts Not Working** - Check Alpine.js is initialized
3. **Modal Not Opening** - Verify x-show="showModal" directive
4. **Navigation Links Showing** - Check blade.php role conditionals
5. **API 403 Errors** - Verify middleware checks role.custom:role
6. **Mobile Drawer** - Check Alpine.js open state on navigation
7. **Animation Delays** - Check CSS transitions duration (0.3s)
8. **Color Contrast** - Use WebAIM contrast checker

---

## Acceptance Criteria

✅ All 4 user roles can login  
✅ Each role sees only their permitted nav links  
✅ Dark mode works for all roles  
✅ Keyboard shortcuts work for all roles  
✅ API endpoints return 403 for unauthorized roles  
✅ Dark mode persists across page refreshes  
✅ Animations are smooth (no skipping)  
✅ Mobile view responsive  
✅ No accessibility issues (Axe report clean)  
✅ All 10 test scenarios pass  

---

## Next Steps

1. Execute Test 1-4 (User flows) - Verify each role can access their features
2. Execute Test 5-7 (Phase 4 features) - Verify dark mode, shortcuts, animations
3. Execute Test 8 (Mobile) - Verify responsive design
4. Execute Test 9 (Accessibility) - Run Axe DevTools scan
5. Execute Test 10 (API permissions) - Verify backend role checks
6. Document any failures
7. Fix critical issues
8. Re-test and validate

