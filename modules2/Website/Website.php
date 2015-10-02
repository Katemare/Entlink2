<?
namespace Pokeliga\Website;

class Feeder // отвечает за область сообщений
exec - представительный запрос.

возможные правила:

Время: Свежие; случайные.
Отбор: Поданные; принятые; популярные (кол-во просмотров); обсуждаемые (кол-во комментов); народный выбор (кол-во лайков); избранные (коллекции модеров); релевантные (касаются пользователя); отмеченные (по меткам); из временного отрезка.
Источник: область сообщений (определяется запросом)

class Feed // лента из одного источника.
freq - частота запроса.
notify - включены нотификации даже при неактивной вкладке; можно настроить мигание или звук.
feeder - ссылка на область сообщений.
criteria - массив критериев отбора, уточняющих область сообщений.
pick - свежие или случайные.

class FeedSet // набор лент
набор задаёт общие настройки для notify, остальное у лент может быть разное.
title

class Widget // фрагмент веб-страницы, который может быть настроен пользователями и/или админами. виджеты включаются в шаблоны кодовым словом {{widgetspace}}, которое только задаёт название и размер (?) поля с виджетами. виджет может содержать фиксированное содержимое, контекстное или ленту.

?>