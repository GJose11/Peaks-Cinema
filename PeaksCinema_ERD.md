# PeaksCinema Database Entity Relationship Diagram

## Database Overview
**Database Name:** `peakscinemadb`  
**System:** Cinema Booking and Management Platform  
**Primary Purpose:** Movie ticket booking, seat management, customer notifications, and cinema operations

---

## Core Entity Tables

### 1. 🏢 **mall**
**Purpose:** Stores cinema mall/location information
```sql
CREATE TABLE mall (
    Mall_ID INT PRIMARY KEY AUTO_INCREMENT,
    MallName VARCHAR(100) NOT NULL UNIQUE,
    Location TEXT NOT NULL
);
```

**Relationships:**
- One-to-Many with `theater` (A mall can have multiple theaters)

---

### 2. 🎬 **movie**
**Purpose:** Stores movie details and metadata
```sql
CREATE TABLE movie (
    Movie_ID INT PRIMARY KEY AUTO_INCREMENT,
    MovieName TEXT NOT NULL,
    MovieDescription MEDIUMTEXT NOT NULL,
    Genre TEXT NOT NULL,
    Rating VARCHAR(10) NOT NULL,
    Runtime INT NOT NULL,
    MoviePoster TEXT NOT NULL,
    MovieAvailability TEXT NOT NULL
);
```

**Relationships:**
- One-to-Many with `timeslot` (A movie can have multiple showtimes)

---

### 3. 🎭 **theater**
**Purpose:** Stores individual theater/cinema hall information
```sql
CREATE TABLE theater (
    Theater_ID INT PRIMARY KEY AUTO_INCREMENT,
    Mall_ID INT NOT NULL,
    TheaterName VARCHAR(100) NOT NULL,
    TotalSeats INT NOT NULL,
    TheaterType VARCHAR(50) NOT NULL,
    FOREIGN KEY (Mall_ID) REFERENCES mall(Mall_ID) ON DELETE CASCADE
);
```

**Relationships:**
- Many-to-One with `mall` (Belongs to one mall)
- One-to-Many with `seats` (Has multiple seats)
- One-to-Many with `timeslot` (Hosts multiple showtimes)

---

### 4. 🎫 **timeslot**
**Purpose:** Stores movie screening schedules
```sql
CREATE TABLE timeslot (
    TimeSlot_ID INT PRIMARY KEY AUTO_INCREMENT,
    StartTime TEXT NOT NULL,
    EndTime TEXT NOT NULL,
    Date TEXT NOT NULL,
    ScreeningType VARCHAR(5) NOT NULL,
    Movie_ID INT NOT NULL,
    Theater_ID INT NOT NULL,
    FOREIGN KEY (Movie_ID) REFERENCES movie(Movie_ID) ON DELETE CASCADE,
    FOREIGN KEY (Theater_ID) REFERENCES theater(Theater_ID) ON DELETE CASCADE
);
```

**Relationships:**
- Many-to-One with `movie` (Shows one movie)
- Many-to-One with `theater` (Plays in one theater)
- One-to-Many with `seats` (Has seat availability)
- One-to-Many with `ticket` (Sells multiple tickets)
- One-to-Many with `food_orders` (Can include food orders)

---

### 5. 💺 **seats**
**Purpose:** Stores seat layout and availability for each timeslot
```sql
CREATE TABLE seats (
    Seat_ID INT PRIMARY KEY AUTO_INCREMENT,
    SeatRow VARCHAR(10) NOT NULL,
    SeatColumn VARCHAR(10) NOT NULL,
    SeatType VARCHAR(50) NOT NULL,
    SeatAvailability INT(1) DEFAULT NULL,
    SeatPrice TEXT DEFAULT NULL,
    Theater_ID INT NOT NULL,
    TimeSlot_ID INT DEFAULT NULL,
    FOREIGN KEY (Theater_ID) REFERENCES theater(Theater_ID) ON DELETE CASCADE,
    FOREIGN KEY (TimeSlot_ID) REFERENCES timeslot(TimeSlot_ID) ON DELETE CASCADE
);
```

**Relationships:**
- Many-to-One with `theater` (Belongs to one theater)
- Many-to-One with `timeslot` (Specific to one showtime)
- One-to-One with `ticket` (Each seat can have one ticket)

---

## Customer & Booking Tables

### 6. 👤 **customer**
**Purpose:** Stores customer information and preferences
```sql
CREATE TABLE customer (
    Customer_ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL,
    PhoneNumber VARCHAR(10) NOT NULL,
    CountryCode VARCHAR(4) NOT NULL,
    PaymentMethod TEXT NOT NULL
);
```

**Relationships:**
- One-to-Many with `ticket` (Can book multiple tickets)
- One-to-Many with `notifications` (Receives notifications)
- One-to-Many with `food_orders` (Can order food)

---

### 7. 🎟️ **ticket**
**Purpose:** Stores individual ticket bookings
```sql
CREATE TABLE ticket (
    Ticket_ID INT PRIMARY KEY AUTO_INCREMENT,
    Seat_ID INT NOT NULL,
    Customer_ID INT NOT NULL,
    Movie_ID INT NOT NULL,
    TimeSlot_ID INT NOT NULL,
    Price DECIMAL(10,2) NOT NULL,
    Status INT NOT NULL,
    DateTime DATETIME NOT NULL
);
```

**Relationships:**
- Many-to-One with `seats` (One seat per ticket)
- Many-to-One with `customer` (Belongs to one customer)
- Many-to-One with `movie` (For one movie)
- Many-to-One with `timeslot` (For one showtime)
- One-to-One with `payment` (Has one payment record)

---

### 8. 💳 **payment**
**Purpose:** Stores payment transaction details
```sql
CREATE TABLE payment (
    Payment_ID INT PRIMARY KEY AUTO_INCREMENT,
    Ticket_ID INT NOT NULL,
    PaymentMethod VARCHAR(50) NOT NULL,
    AmountPaid DECIMAL(10,2) NOT NULL,
    PaymentDate DATE NOT NULL,
    PaymentStatus INT NOT NULL
);
```

**Relationships:**
- One-to-One with `ticket` (Pays for one ticket)
- One-to-One with `e-receipt` (Generates one receipt)

---

### 9. 🧾 **e-receipt**
**Purpose:** Stores electronic receipt information
```sql
CREATE TABLE `e-receipt` (
    Receipt_ID INT PRIMARY KEY AUTO_INCREMENT,
    PaymentID INT NOT NULL,
    DateIssued DATE NOT NULL,
    SentToEmail VARCHAR(100) NOT NULL,
    ReceiptStatus INT NOT NULL,
    Status INT NOT NULL
);
```

**Relationships:**
- One-to-One with `payment` (Generated for one payment)

---

## System Management Tables

### 10. 🔔 **notifications**
**Purpose:** Stores customer notifications
```sql
CREATE TABLE notifications (
    Notif_ID INT PRIMARY KEY AUTO_INCREMENT,
    Customer_ID INT NOT NULL,
    Title TEXT NOT NULL,
    Message TEXT NOT NULL,
    Type TEXT NOT NULL,
    IsRead BOOLEAN DEFAULT FALSE,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Customer_ID) REFERENCES customer(Customer_ID) ON DELETE CASCADE
);
```

**Relationships:**
- Many-to-One with `customer` (Belongs to one customer)

**Notification Types:**
- `general` - System announcements
- `booking` - Booking confirmations
- `cancellation` - Booking cancellations
- `reminder` - Showtime reminders

---

### 11. 🍿 **food_orders**
**Purpose:** Stores food and beverage orders
```sql
CREATE TABLE food_orders (
    Order_ID INT PRIMARY KEY AUTO_INCREMENT,
    Customer_ID INT NOT NULL,
    TimeSlot_ID INT NOT NULL,
    Item_ID INT NOT NULL,
    Quantity INT NOT NULL,
    UnitPrice DECIMAL(10,2) NOT NULL,
    BookingRef VARCHAR(50) NOT NULL,
    OrderTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Customer_ID) REFERENCES customer(Customer_ID) ON DELETE CASCADE,
    FOREIGN KEY (TimeSlot_ID) REFERENCES timeslot(TimeSlot_ID) ON DELETE CASCADE
);
```

**Relationships:**
- Many-to-One with `customer` (Ordered by one customer)
- Many-to-One with `timeslot` (For one showtime)

---

### 12. ⏱️ **cinema_queue**
**Purpose:** Tracks real-time queue information for different areas
```sql
CREATE TABLE cinema_queue (
    Queue_ID INT PRIMARY KEY AUTO_INCREMENT,
    mall_id INT NOT NULL,
    area VARCHAR(50) NOT NULL,
    status ENUM('open', 'closed', 'maintenance') NOT NULL,
    queue_length INT DEFAULT 0,
    current_wait_mins INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mall_id) REFERENCES mall(Mall_ID) ON DELETE CASCADE
);
```

**Queue Areas:**
- `ticketing` - Ticket purchase queue
- `concessions` - Food/beverage queue  
- `restroom` - Restroom facilities queue

**Relationships:**
- Many-to-One with `mall` (Belongs to one mall)

---

### 13. 📬 **reminders_sent**
**Purpose:** Tracks sent reminder notifications
```sql
CREATE TABLE reminders_sent (
    Reminder_ID INT PRIMARY KEY AUTO_INCREMENT,
    Customer_ID INT NOT NULL,
    BookingRef VARCHAR(50) NOT NULL,
    ReminderType ENUM('showtime', 'payment', 'pickup') NOT NULL,
    SentAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('sent', 'delivered', 'failed') DEFAULT 'sent',
    FOREIGN KEY (Customer_ID) REFERENCES customer(Customer_ID) ON DELETE CASCADE
);
```

**Relationships:**
- Many-to-One with `customer` (Sent to one customer)

---

## Relationship Summary

### Primary Key Relationships
```
mall (1) ──────── (N) theater (1) ──────── (N) seats
 │                                      │
 │                                      │
 └── (N) timeslot (1) ──────── (N) ticket ──────── (1) payment ──────── (1) e-receipt
        │                                      │
        │                                      │
movie (1) ──────── (N) timeslot              customer (1) ──────── (N) ticket
        │                                      │
        │                                      │
        └── (N) food_orders                    └── (N) notifications
                                               │
                                               └── (N) reminders_sent
```

### Supporting Tables
```
mall (1) ──────── (N) cinema_queue
```

---

## Data Flow & Business Logic

### 1. **Booking Flow**
1. Customer selects movie → `movie` table
2. Choose timeslot → `timeslot` table  
3. Select seats → `seats` table (availability check)
4. Create ticket → `ticket` table
5. Process payment → `payment` table
6. Generate receipt → `e-receipt` table
7. Send notification → `notifications` table

### 2. **Showtime Management**
- Movies scheduled in `timeslot` with theater assignments
- Seat availability managed in `seats` table per timeslot
- Real-time queue tracking in `cinema_queue`

### 3. **Customer Communication**
- Notifications stored in `notifications` table
- Reminders tracked in `reminders_sent` table
- Food orders linked to customer bookings

---

## Indexes & Performance Considerations

### Recommended Indexes
```sql
-- For booking queries
CREATE INDEX idx_timeslot_movie_date ON timeslot(Movie_ID, Date);
CREATE INDEX idx_seats_availability ON seats(SeatAvailability, TimeSlot_ID);

-- For customer queries  
CREATE INDEX idx_ticket_customer ON ticket(Customer_ID, DateTime);
CREATE INDEX idx_notifications_customer ON notifications(Customer_ID, IsRead);

-- For queue tracking
CREATE INDEX idx_queue_mall_area ON cinema_queue(mall_id, area);
```

### Foreign Key Constraints
- All foreign keys use `ON DELETE CASCADE` for data integrity
- Referential integrity maintained across all related tables

---

## Security & Data Privacy

### Sensitive Data Fields
- `customer.Email`, `customer.PhoneNumber` - PII
- `payment.AmountPaid`, `payment.PaymentMethod` - Financial data
- `notifications.Message` - Communication content

### Recommended Security Measures
- Encrypt sensitive PII fields
- Implement audit logging for payment transactions
- Regular data backup and retention policies
- GDPR compliance for customer data

---

## ERD Visualization Notes

### Entity Categories
- **🏢 Location:** mall, theater
- **🎬 Content:** movie, timeslot  
- **💺 Seating:** seats
- **👤 Users:** customer
- **🎟️ Transactions:** ticket, payment, e-receipt
- **🔔 Communications:** notifications, reminders_sent
- **🍿 Services:** food_orders, cinema_queue

### Relationship Types
- **One-to-Many:** Most common (mall→theater, movie→timeslot, etc.)
- **Many-to-Many:** Implemented through junction tables
- **One-to-One:** ticket↔payment, payment↔e-receipt

This ERD provides a comprehensive overview of the PeaksCinema database structure, supporting the full cinema booking and management ecosystem.
