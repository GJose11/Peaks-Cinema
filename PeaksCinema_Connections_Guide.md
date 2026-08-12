# 🎬 PeaksCinema Database Connections Guide
## Complete Table Relationships for Professor Presentation

---

## 🎯 **OVERVIEW: How Everything Connects**

Think of this as a **cinema ecosystem** where each table plays a specific role:

```
🏢 MALL (Building) → 🎭 THEATER (Room) → 📅 TIMESLOT (Schedule) → 💺 SEAT (Chair)
     │                     │                      │                     │
     └─────────────────────┴──────────────────────┴─────────────────────┘
                              ↓
                        🎟️ TICKET (Booking)
                              ↓
                        👤 CUSTOMER (Person)
                              ↓
                        💳 PAYMENT (Money)
```

---

## 📊 **SECTION 1: PHYSICAL INFRASTRUCTURE**

### **🏢 MALL → 🎭 THEATER → 💺 SEATS**

**Connection Flow:**
```
mall (1) ─────────── (∞) theater (1) ─────────── (∞) seats
```

**How to Explain:**
> *"Ma'am, our system starts with the physical infrastructure. A **mall** (like SM Marikina) contains multiple **theaters** (cinema halls), and each **theater** has many **seats**. This is a classic one-to-many relationship."*

**Real Example:**
- **Mall**: "SM Marikina" (Mall_ID = 1)
- **Theaters**: "Director's Club 1", "Cinema 2", "Cinema 3" (Theater_ID = 13, 14, 15)
- **Seats**: "A1", "A2", "B1", "B2" in each theater (Seat_ID = 353, 354, 355...)

**Foreign Key Connections:**
```sql
theater.Mall_ID → mall.Mall_ID
seats.Theater_ID → theater.Theater_ID
```

---

## 📊 **SECTION 2: CONTENT & SCHEDULING**

### **🎬 MOVIE → 📅 TIMESLOT**

**Connection Flow:**
```
movie (1) ─────────── (∞) timeslot (∞) ─────────── (1) theater
```

**How to Explain:**
> *"Each **movie** can have multiple **timeslots** (showtimes), and each **timeslot** happens in one specific **theater**. This allows us to show the same movie at different times and in different theaters."*

**Real Example:**
- **Movie**: "Superman" (Movie_ID = 1)
- **Timeslots**: 
  - Nov 20, 6:30 PM (TimeSlot_ID = 14, Theater_ID = 13)
  - Nov 22, 1:50 PM (TimeSlot_ID = 15, Theater_ID = 13)
  - Nov 21, 6:50 PM (TimeSlot_ID = 16, Theater_ID = 13)

**Foreign Key Connections:**
```sql
timeslot.Movie_ID → movie.Movie_ID
timeslot.Theater_ID → theater.Theater_ID
```

---

## 📊 **SECTION 3: THE BOOKING PROCESS**

### **👤 CUSTOMER → 🎟️ TICKET → 💺 SEATS**

**Connection Flow:**
```
customer (1) ─────────── (∞) ticket (∞) ─────────── (1) seats
```

**How to Explain:**
> *"When a **customer** books, they create a **ticket** record that links them to a specific **seat** for a specific showtime. Each seat can only be booked once per showtime."*

**Real Example:**
- **Customer**: "John Doe" (Customer_ID = 123)
- **Ticket**: Books seat A1 for Superman at 6:30 PM (Ticket_ID = 456, Seat_ID = 353)
- **Seat**: "A1" in Director's Club 1 (Seat_ID = 353, now unavailable)

**Foreign Key Connections:**
```sql
ticket.Customer_ID → customer.Customer_ID
ticket.Seat_ID → seats.Seat_ID
ticket.Movie_ID → movie.Movie_ID
ticket.TimeSlot_ID → timeslot.TimeSlot_ID
```

---

## 📊 **SECTION 4: PAYMENT & RECEIPTS**

### **🎟️ TICKET → 💳 PAYMENT → 🧾 E-RECEIPT**

**Connection Flow:**
```
ticket (1) ─────────── (1) payment (1) ─────────── (1) e-receipt
```

**How to Explain:**
> *"Each **ticket** has exactly one **payment** record, and each **payment** generates one **e-receipt**. This creates a complete audit trail for every transaction."*

**Real Example:**
- **Ticket**: Superman booking (Ticket_ID = 456, Price = ₱350)
- **Payment**: Credit card payment (Payment_ID = 789, Amount = ₱350)
- **Receipt**: Digital receipt sent via email (Receipt_ID = 101, PaymentID = 789)

**Foreign Key Connections:**
```sql
payment.Ticket_ID → ticket.Ticket_ID
`e-receipt`.PaymentID → payment.Payment_ID
```

---

## 📊 **SECTION 5: CUSTOMER COMMUNICATIONS**

### **👤 CUSTOMER → 🔔 NOTIFICATIONS & 📬 REMINDERS**

**Connection Flow:**
```
customer (1) ─────────── (∞) notifications
customer (1) ─────────── (∞) reminders_sent
```

**How to Explain:**
> *"Our system keeps customers informed through **notifications** (booking confirmations, cancellations) and **reminders** (showtime alerts). All communications are tracked per customer."*

**Real Example:**
- **Customer**: John Doe (Customer_ID = 123)
- **Notifications**: 
  - "Booking confirmed for Superman" (Notif_ID = 1)
  - "Your movie starts in 1 hour" (Notif_ID = 2)
- **Reminders**: Showtime reminder sent 30 minutes before (Reminder_ID = 1)

**Foreign Key Connections:**
```sql
notifications.Customer_ID → customer.Customer_ID
reminders_sent.Customer_ID → customer.Customer_ID
```

---

## 📊 **SECTION 6: ADDITIONAL SERVICES**

### **👤 CUSTOMER + 📅 TIMESLOT → 🍿 FOOD_ORDERS**

**Connection Flow:**
```
customer (1) ─────────── (∞) food_orders (∞) ─────────── (1) timeslot
```

**How to Explain:**
> *"Customers can order food and beverages for specific showtimes. Each **food_order** links a **customer** to a **timeslot**, allowing us to track concession sales per movie."*

**Real Example:**
- **Customer**: John Doe (Customer_ID = 123)
- **Timeslot**: Superman 6:30 PM (TimeSlot_ID = 14)
- **Food Order**: 1x Popcorn, 2x Soda (Order_ID = 555, Total = ₱200)

**Foreign Key Connections:**
```sql
food_orders.Customer_ID → customer.Customer_ID
food_orders.TimeSlot_ID → timeslot.TimeSlot_ID
```

### **🏢 MALL → ⏱️ CINEMA_QUEUE**

**Connection Flow:**
```
mall (1) ─────────── (∞) cinema_queue
```

**How to Explain:**
> *"Each **mall** has real-time **queue** tracking for different areas (ticketing, concessions, restrooms). This helps customers plan their visit and reduces wait times."*

**Real Example:**
- **Mall**: SM Marikina (Mall_ID = 1)
- **Queue Areas**: 
  - Ticketing: 5 people, 10 min wait
  - Concessions: 8 people, 15 min wait
  - Restroom: 2 people, 5 min wait

**Foreign Key Connections:**
```sql
cinema_queue.mall_id → mall.Mall_ID
```

---

## 🎯 **COMPLETE DATA FLOW DIAGRAM**

```
🏢 MALL
   │ (has many)
   ▼
🎭 THEATER ──────────── 📅 TIMESLOT ──────────── 🎬 MOVIE
   │                       │                       │
   │ (has many)            │ (scheduled in)         │ (shown in)
   ▼                       ▼                       ▼
💺 SEATS                  🎟️ TICKET ────────────────┘
   │                       │
   │ (booked by)            │ (belongs to)
   ▼                       ▼
   └─────────── 👤 CUSTOMER ──────────── 🔔 NOTIFICATIONS
                   │                       │
                   │ (pays for)             │ (receives)
                   ▼                       ▼
                  💳 PAYMENT ────────── 🍿 FOOD_ORDERS
                   │
                   │ (generates)
                   ▼
                  🧾 E-RECEIPT
```

---

## 🎓 **KEY POINTS FOR PROFESSOR**

### **1. Normalization Excellence**
> *"Ma'am, we've eliminated data redundancy. Movie details stored once, but can be referenced in multiple showtimes."*

### **2. Referential Integrity**
> *"All foreign key constraints ensure data consistency. You can't book a seat that doesn't exist or pay for a ticket that isn't there."*

### **3. Scalability**
> *"The design supports adding new malls, theaters, and movies without changing the database structure."*

### **4. Complete Audit Trail**
> *"Every transaction from ticket booking to payment is tracked, creating a complete business audit trail."*

### **5. Customer-Centric Design**
> *"All customer interactions (bookings, payments, notifications, food orders) are linked to the customer record for 360-degree view."*

---

## 🔍 **QUERY EXAMPLES TO SHOW RELATIONSHIPS**

### **Find all movies showing at a specific mall:**
```sql
SELECT m.MovieName, t.StartTime, th.TheaterName
FROM movie m
JOIN timeslot t ON m.Movie_ID = t.Movie_ID
JOIN theater th ON t.Theater_ID = th.Theater_ID
WHERE th.Mall_ID = 1;
```

### **Get customer's complete booking history:**
```sql
SELECT c.Name, m.MovieName, t.Date, p.AmountPaid
FROM customer c
JOIN ticket t ON c.Customer_ID = t.Customer_ID
JOIN movie m ON t.Movie_ID = m.Movie_ID
JOIN payment p ON t.Ticket_ID = p.Ticket_ID
WHERE c.Customer_ID = 123;
```

---

## 🎯 **PRESENTATION TIPS**

1. **Start with the physical infrastructure** (mall → theater → seats)
2. **Add the content layer** (movies → timeslots)
3. **Bring in the customer** (customer → tickets)
4. **Complete the transaction** (payment → receipts)
5. **Add the services** (notifications, food, queues)

**Remember:** *"Every table serves a specific business purpose, and every relationship maintains data integrity while supporting the complete cinema experience."*

This structure ensures **no data duplication**, **complete traceability**, and **efficient operations** - exactly what a modern cinema management system needs!
