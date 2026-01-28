import csv
import os
from datetime import datetime

import matplotlib.dates as mdates
import matplotlib.pyplot as plt
import pandas as pd
import pygame
import sys

BASE_DIR = os.path.dirname(__file__)

startmenge = 0

csv_name = os.path.join(BASE_DIR, "visitors.csv")
image_path = os.path.join(BASE_DIR, "visitors.jpg")
try:
    plt.style.use('seaborn-v0_8-whitegrid')
except OSError:
    plt.style.use('default')

def ensure_csv_exists():
    if not os.path.exists(csv_name):
        with open(csv_name, "x") as f:
            f.write("uhrzeit,visitors\n")
            f.write(datetime.now().strftime("%H:%M:%S"))
            f.write(f",{startmenge}\n")
        return True
    return False


pygame.init()

fps = 60
fpsClock = pygame.time.Clock()
width, height = 1200, 800

screen = pygame.display.set_mode((width,height))
pygame.display.set_caption("Knecht Counter")
title_font = pygame.font.Font(None, 44)
big_font = pygame.font.Font(None, 92)
font = pygame.font.Font(None, 40)
small_font = pygame.font.Font(None, 24)

objects = []

white = "#ffffff"
black = "#111827"
red   = "#f95d6a"
lightgrey = "#a3a9b5"
darkgrey = "#3b4452"
accent = "#2f7bff"
accent_dark = "#1f5fd1"
bg_top = "#f6f7fb"
bg_bottom = "#e9eef6"
card = "#ffffff"
card_border = "#d9e0ea"
plot_bg = "#f8fafc"

plot_dirty = False


class Visitors():
    def __init__(self, visitors=startmenge):
        self.visitors=visitors

    def update(self):
        now = datetime.now().strftime("%H:%M:%S")
        print(f"Visitors anwesend um {now}: ",self.visitors)
        update_csv(self.visitors)

    def plus(self):
        self.visitors+=1
        self.update()

    def minus(self):
        if self.visitors <= 0:
            return
        self.visitors -= 1
        self.update()

    def color(self):
        if self.visitors>=150:
            return red
        else:
            return black
    
    def display(self):
        return font.render("Anwesend", True, darkgrey)

class Button():
    def __init__(self, x, y, width, height, buttonText='Button', onclickFunction=None, fill=accent, hover=accent_dark):
        self.x = x
        self.y = y
        self.width = width
        self.height = height
        self.onclickFunction = onclickFunction
        self.alreadyPressed = False

        self.fillColors = {
            'normal': fill,
            'hover': hover,
            'pressed': lightgrey,
        }

        self.buttonSurface = pygame.Surface((self.width, self.height), pygame.SRCALPHA)
        self.buttonRect = pygame.Rect(self.x, self.y, self.width, self.height)

        self.buttonSurf = font.render(buttonText, True, white)


        objects.append(self)

    def process(self):
        mousePos = pygame.mouse.get_pos()
        self.buttonSurface.fill((0, 0, 0, 0))
        pygame.draw.rect(self.buttonSurface, self.fillColors['normal'], self.buttonSurface.get_rect(), border_radius=24)
        if self.buttonRect.collidepoint(mousePos):
            pygame.draw.rect(self.buttonSurface, self.fillColors['hover'], self.buttonSurface.get_rect(), border_radius=24)
            if pygame.mouse.get_pressed(num_buttons=3)[0]:
                pygame.draw.rect(self.buttonSurface, self.fillColors['pressed'], self.buttonSurface.get_rect(), border_radius=24)
                if not self.alreadyPressed:
                    self.onclickFunction()
                    self.alreadyPressed = True
            else:
                self.alreadyPressed = False

        self.buttonSurface.blit(self.buttonSurf, [
            self.buttonRect.width/2 - self.buttonSurf.get_rect().width/2,
            self.buttonRect.height/2 - self.buttonSurf.get_rect().height/2
        ])
        screen.blit(self.buttonSurface, self.buttonRect)

k = Visitors(startmenge)


zahl = pygame.Rect(120, 120, 400, 120)
zeit = pygame.Rect(940, 80, 200, 40)
plot = pygame.Rect(120, 360, 960, 360)

Button(520, 210, 160, 70, '+1', k.plus, fill=accent, hover=accent_dark)
Button(700, 210, 160, 70, '-1', k.minus, fill=red, hover="#df4150")


def display_time():
    time = datetime.now().strftime("%H:%M:%S")
    return small_font.render(time,True,darkgrey)

def update_csv(wieviele):
    line = [datetime.now().strftime("%H:%M:%S"),wieviele]
    with open(csv_name, 'a', newline='') as f:
        writer=csv.writer(f)
        writer.writerow(line)
    update_plot()


def update_plot():
    global plot_dirty
    plt.close()
    df = pd.read_csv(csv_name)
    df["uhrzeit"]=pd.to_datetime(df["uhrzeit"],format="%H:%M:%S")
    plt.figure(figsize=(13,5), facecolor=plot_bg)
    ax = plt.gca()
    ax.set_facecolor(plot_bg)
    visitors=df["visitors"].tolist()
    plt.plot(df["uhrzeit"].tolist(),visitors,c=accent,linewidth=2.5)
    plt.gcf().autofmt_xdate()
    if max(visitors)>140:
        plt.axhline(y=150,color=red,linewidth=1.5)
    plt.gca().xaxis.set_major_locator(mdates.MinuteLocator(byminute=range(0,60,15)))
    plt.gca().xaxis.set_major_formatter(mdates.DateFormatter('%H:%M'))
    plt.xticks(rotation=45)
    plt.savefig(image_path, bbox_inches='tight')
    plot_dirty = True

csv_created = ensure_csv_exists()
if not csv_created:
    update_csv(startmenge)
else:
    update_plot()

plot_image = pygame.image.load(image_path).convert()
plot_image = pygame.transform.smoothscale(plot_image, (plot.width, plot.height))
plot_dirty = False
background = pygame.Surface((width, height))
for y in range(height):
    t = y / (height - 1)
    c1 = pygame.Color(bg_top)
    c2 = pygame.Color(bg_bottom)
    r1, g1, b1 = c1.r, c1.g, c1.b
    r2, g2, b2 = c2.r, c2.g, c2.b
    r = int(r1 + (r2 - r1) * t)
    g = int(g1 + (g2 - g1) * t)
    b = int(b1 + (b2 - b1) * t)
    pygame.draw.line(background, (r, g, b), (0, y), (width, y))

def draw_card(rect, radius=24):
    shadow = pygame.Surface((rect.width, rect.height), pygame.SRCALPHA)
    pygame.draw.rect(shadow, (0, 0, 0, 30), shadow.get_rect(), border_radius=radius)
    screen.blit(shadow, (rect.x + 2, rect.y + 4))
    pygame.draw.rect(screen, card, rect, border_radius=radius)
    pygame.draw.rect(screen, card_border, rect, width=1, border_radius=radius)

while True:
    screen.blit(background, (0, 0))
    for event in pygame.event.get():
        if event.type == pygame.QUIT:
            pygame.quit()
            sys.exit()
    draw_card(pygame.Rect(80, 60, 1040, 250))
    draw_card(pygame.Rect(80, 330, 1040, 430))
    screen.blit(title_font.render("Visitors Counter", True, black), (120, 80))
    screen.blit(k.display(),zahl)
    screen.blit(big_font.render(str(k.visitors), True, k.color()), (zahl.x, zahl.y + 38))
    screen.blit(display_time(),zeit)
    for object in objects:
        object.process()
    if plot_dirty:
        plot_image = pygame.image.load(image_path).convert()
        plot_image = pygame.transform.smoothscale(plot_image, (plot.width, plot.height))
        plot_dirty = False
    screen.blit(plot_image,plot)
    pygame.display.flip()
    fpsClock.tick(fps)
