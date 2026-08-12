# 🔗 PeaksCinema Key Relationships to Understand
## Most Important Connections - Simple & Clear

---

## 🏢 **PHYSICAL INFRASTRUCTURE**

### **One Mall → Many Theaters**
**SM Marikina has 5 different cinema halls**
```
mall (1) ─────────── (∞) theater
SM Marikina → Director's Club 1, Cinema 2, Cinema 3, Cinema 4, Cinema 5
```

### **One Theater → Many Seats**
**Director's Club 1 has 50 different seats**
```
theater (1) ─────────── (∞) seats
Director's Club 1 → A1, A2, A3, B1, B2, B3... (50 seats total)
```

### **One Theater → Many Timeslots**
**Director's Club 1 hosts many different showtimes**
```
theater (1) ─────────── (∞) timeslot
Director's Club 1 → Superman 1:00PM, Superman 4:00PM, Batman 7:00PM
```

---

## 🎬 **MOVIES & SCHEDULING**

### **One Movie → Many Showtimes**
**Superman plays at 1:00 PM, 4:00 PM, 7:00 PM**
```
movie (1) ─────────── (∞) timeslot
Superman → Nov20 6:30PM, Nov21 1:50PM, Nov22 6:50PM
```

### **One Timeslot → One Movie**
**The 6:30 PM showing only shows Superman**
```
timeslot (1) ─────────── (1) movie
Nov20 6:30PM → Superman
```

### **One Timeslot → One Theater**
**The 6:30 PM Superman shows only in Director's Club 1**
```
timeslot (1) ─────────── (1) theater
Nov20 6:30PM Superman → Director's Club 1
```

---

## 👤 **CUSTOMERS & BOOKINGS**

### **One Customer → Many Tickets**
**John Doe can book multiple tickets over time**
```
customer (1) ─────────── (∞) ticket
John Doe → Superman ticket, Batman ticket, Spider-Man ticket
```

### **One Customer → Many Notifications**
**John Doe receives many different notifications**
```
customer (1) ─────────── (∞) notifications
John Doe → Booking confirmed, Showtime reminder, Payment received
```

### **One Customer → Many Food Orders**
**John Doe can order food for different movies**
```
customer (1) ─────────── (∞) food_orders
John Doe → Popcorn for Superman, Soda for Batman, Combo for Spider-Man
```

### **One Customer → Many Reminders**
**John Doe gets multiple reminder messages**
```
customer (1) ─────────── (∞) reminders_sent
John Doe → 30min reminder, 1day reminder, Payment reminder
```

---

## 🎟️ **TICKETS & SEATS**

### **One Ticket → One Seat**
**Each booking reserves exactly one specific seat**
```
ticket (1) ─────────── (1) seats
Ticket #456 → Seat A1
```

### **One Ticket → One Customer**
**Each ticket belongs to exactly one customer**
```
ticket (1) ─────────── (1) customer
Ticket #456 → John Doe
```

### **One Ticket → One Movie**
**Each ticket is for exactly one movie**
```
ticket (1) ─────────── (1) movie
Ticket #456 → Superman
```

### **One Ticket → One Timeslot**
**Each ticket is for exactly one showtime**
```
ticket (1) ─────────── (1) timeslot
Ticket #456 → Nov20 6:30PM
```

### **One Timeslot → Many Seats**
**The 7:00 PM showing has 50 available seats**
```
timeslot (1) ─────────── (∞) seats
Nov20 6:30PM → A1, A2, A3, B1, B2, B3... (50 seats)
```

### **One Seat → Many Tickets (over time)**
**Seat A1 can be booked for different showtimes**
```
seat (1) ─────────── (∞) ticket
Seat A1 → Superman 6:30PM ticket, Batman 9:00PM ticket, Spider-Man 2:00PM ticket
```

---

## 💳 **PAYMENTS & RECEIPTS**

### **One Ticket → One Payment**
**Each booking has exactly one payment**
```
ticket (1) ─────────── (1) payment
Ticket #456 → Payment #789
```

### **One Payment → One Receipt**
**Each payment generates exactly one digital receipt**
```
payment (1) ─────────── (1) e-receipt
Payment #789 → Receipt #101
```

### **One Customer → Many Payments**
**John Doe makes multiple payments over time**
```
customer (1) ─────────── (∞) payment
John Doe → Superman payment, Batman payment, Spider-Man payment
```

---

## 🍿 **FOOD & SERVICES**

### **One Timeslot → Many Food Orders**
**The 6:30 PM Superman showing has many food orders**
```
timeslot (1) ─────────── (∞) food_orders
Nov20 6:30PM → John's popcorn, Jane's soda, Mike's combo
```

### **One Customer → Many Food Orders**
**John Doe orders food for different visits**
```
customer (1) ─────────── (∞) food_orders
John Doe → Popcorn for Superman, Nachos for Batman, Hotdog for Spider-Man
```

---

## ⏱️ **QUEUE MANAGEMENT**

### **One Mall → Many Queue Areas**
**SM Marikina has multiple queue tracking points**
```
mall (1) ─────────── (∞) cinema_queue
SM Marikina → Ticketing queue, Concessions queue, Restroom queue
```

### **One Queue Area → Many Updates**
**The ticketing queue gets updated many times per day**
```
cinema_queue (1) ─────────── (∞) updates
Ticketing queue → 9AM update, 12PM update, 6PM update, 9PM update
```

---

## 🎯 **COMPLETE WORKFLOW EXAMPLES**

### **Full Customer Journey:**
```
John Doe (customer) ──→ Superman (movie)
      │                      │
      └─── Ticket #456 ─────┘
            │
            ├─── Seat A1 (seats)
            ├─── Nov20 6:30PM (timeslot)
            ├─── Director's Club 1 (theater)
            └─── SM Marikina (mall)
      
Ticket #456 ──→ Payment #789 ──→ Receipt #101
      │
      └─── Popcorn order (food_orders)
      
John Doe ──→ "Booking confirmed" (notifications)
      └─── "Showtime starts in 30min" (reminders_sent)
```

### **Movie Scheduling Flow:**
```
Superman (movie) ──→ Nov20 6:30PM (timeslot) ──→ Director's Club 1 (theater) ──→ SM Marikina (mall)
      │                      │                           │
      └─── Nov21 1:50PM ─────┘                           │
      │                                                  │
      └─── Nov22 6:50PM ──────────────────────────────────┘
```

---

## 🔑 **KEY TAKEAWAYS**

### **One-to-Many (Most Common):**
- Mall → Theaters, Seats, Timeslots, Queues
- Movie → Timeslots, Tickets
- Customer → Tickets, Payments, Notifications, Food Orders, Reminders
- Theater → Seats, Timeslots
- Timeslot → Seats, Tickets, Food Orders

### **One-to-One (Exact Matches):**
- Ticket ↔ Payment ↔ Receipt
- Ticket ↔ Seat ↔ Customer ↔ Movie ↔ Timeslot
- Timeslot ↔ Movie ↔ Theater

### **Many-to-Many (Through Junction Tables):**
- Movies ↔ Theaters (through timeslots)
- Customers ↔ Movies (through tickets)
- Customers ↔ Showtimes (through tickets + food orders)

---

## 💡 **Simple Rules to Remember:**

1. **Physical things** (mall, theater, seat) have **one-to-many** relationships
2. **Transactions** (ticket, payment, receipt) are **one-to-one**
3. **People** (customer) connect to **many things** over time
4. **Events** (timeslot) connect **physical** and **content** data
5. **Communications** (notifications) always link back to a **person**

This structure ensures **no data duplication** while maintaining **complete traceability** of every cinema operation!
